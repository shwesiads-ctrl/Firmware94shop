<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 2.2 — proposal for the long-flagged "deposits.transaction_hash has no
 * uniqueness constraint" gap (originally noted, not fixed, in Phase 2.1's
 * audit; re-investigated per the Phase 2.2 master prompt §8).
 *
 * Why not a plain `unique()` on `transaction_hash`:
 *   1. Normalization: a hash resubmitted with different case (hex can be
 *      upper/lower) or a stray "0x" prefix on an EVM-style BEP20 hash would
 *      not collide at the DB layer even though it's the same on-chain
 *      transaction. A unique index must sit on a *normalized* value, not the
 *      raw user-submitted string.
 *   2. Network scope: TRC20 and BEP20 are different chains. A dedupe key
 *      should be (network, hash), not hash alone — a global unique on the
 *      raw column would also wrongly forbid a manual ("other"/no-network)
 *      deposit row that happens to carry no hash at all if more than one
 *      such row exists (both NULL) — MySQL/MariaDB already treat multiple
 *      NULLs as distinct under a unique index, so that part is fine as long
 *      as the generated key is also NULL when there's no hash to dedupe.
 *   3. Deposit lifecycle: a REJECTED deposit (e.g. the user mistyped the
 *      hash and admin rejected it) must not permanently block the *correct*
 *      hash from being submitted afterwards. The dedupe key must therefore
 *      exclude rejected rows — only pending/approved rows should compete for
 *      uniqueness.
 *   4. Database compatibility: target stack is "MySQL 8 / MariaDB
 *      compatible". Unlike PostgreSQL, MySQL/MariaDB have no native partial/
 *      filtered unique index. The portable way to express "unique only
 *      among non-rejected rows" on this engine family is a stored generated
 *      column that evaluates to NULL for rejected rows (and for rows with no
 *      hash), with the unique index placed on that generated column instead
 *      of on `transaction_hash` directly. MySQL 8.0.13+ and MariaDB 10.2+
 *      both support stored generated columns and indexing them.
 *
 * This migration is ADDITIVE ONLY: it does not alter the historical
 * 2025_01_01_001700 migration, drops cleanly in `down()`, and was NOT
 * executed in this sandbox (no DB server reachable — see
 * docs/PHASE2_2_DATABASE_REPORT.md). It is a reviewed proposal, not a
 * verified change. Before running it against any real database, first run
 * the duplicate-detection query documented in that same report.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Stored generated column: NULL unless there's a hash to dedupe on a
        // non-rejected deposit. Normalization (lowercasing / trimming) is
        // also applied on write by App\Services\Payments\DepositService, so
        // this expression mirrors that by lower-casing defensively in case a
        // row is ever written by a path other than that service (e.g. a
        // future import script).
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('transaction_hash_dedupe_key', 160)
                ->nullable()
                ->storedAs(
                    "CASE
                        WHEN status = 'rejected' OR transaction_hash IS NULL THEN NULL
                        ELSE LOWER(CONCAT(COALESCE(network, ''), ':', transaction_hash))
                    END"
                )
                ->after('transaction_hash');
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->unique('transaction_hash_dedupe_key', 'deposits_tx_hash_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropUnique('deposits_tx_hash_dedupe_unique');
            $table->dropColumn('transaction_hash_dedupe_key');
        });
    }
};
