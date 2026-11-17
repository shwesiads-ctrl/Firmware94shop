# Phase 2.2 — Environment Report

**Branch:** `test/phase-2.2` (created from `audit/phase-2.1` @ `d580813`)
**Date:** 2026-11-17
**Status: PARTIALLY VERIFIED**

This report records the exact, actually-executed output of every environment-baseline command. Nothing here is estimated or assumed.

## 1. OS / Runtime Baseline (executed)

| Component | Version | Source |
|---|---|---|
| OS | Ubuntu 24.04.5 LTS (Noble Numbat) | `/etc/os-release` |
| PHP | 8.3.6 (cli, NTS) with Zend OPcache 8.3.6 | `php -v` |
| Composer | 2.8.12 (2025-09-19) | `composer --version` |
| Node.js | v22.23.4 | `node --version` |
| npm | 10.9.9 | `npm --version` |
| Redis server | 7.0.15 (jemalloc-5.3.0, 64-bit) | `redis-server --version` |
| Redis CLI | 7.0.15 | `redis-cli --version` |
| Git | 2.43.0 | `git --version` |
| MySQL client | **NOT INSTALLED** | `mysql --version` → `command not found` |
| MariaDB client | **NOT INSTALLED** | `mariadb --version` → `command not found` |
| MySQL/MariaDB server | **NOT INSTALLED** | no `mysqld`/`mariadbd` binary found anywhere on filesystem |
| Disk space | 31G available / 252G total (29% used) | `df -h /` |

## 2. PHP Extensions (executed: `php -m`)

Present: calendar, Core, ctype, curl, date, dom, exif, FFI, fileinfo, filter, ftp, gd, gettext, hash, iconv, igbinary, intl, json, libxml, mbstring, mysqli, mysqlnd, openssl, pcntl, pcre, PDO, pdo_mysql, pdo_pgsql, pdo_sqlite, pgsql, Phar, posix, random, readline, redis, Reflection, session, shmop, SimpleXML, sockets, sodium, SPL, sqlite3, standard, sysvmsg, sysvsem, sysvshm, tokenizer, xml, xmlreader, xmlwriter, xsl, Zend OPcache, zlib, zip.

**Laravel 12 required extensions** (Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML): all **present**. ✅

### ⚠️ CRITICAL FINDING — BUG-007 (P0, new in Phase 2.2)

**`ext-bcmath` is NOT installed**, and is also **not declared** in `composer.json`'s `require` block.

`app/Services/Wallet/WalletService.php` calls `bccomp()`, `bcsub()`, and `bcadd()` directly (lines 50, 54, 77, 101, 105, 126) — i.e. **every single wallet credit, debit, hold, and release operation** depends on this extension. With `ext-bcmath` absent, every one of those calls would throw `Error: Call to undefined function bcadd()` the moment it executes — a fatal, unrecoverable failure of the platform's entire financial core, not a degraded-mode issue.

Because `composer.json` never declared `"ext-bcmath": "*"`, `composer check-platform-reqs` would **not** have caught this before deploy — it only checks extensions actually referenced in composer's metadata. This is a packaging gap independent of the sandbox's own missing extension.

- **Root cause:** extension never declared as a platform requirement; sandbox image does not ship it; PHP 8.3 does not bundle bcmath by default on all distros.
- **Impact:** total loss of wallet functionality (deposits, order debits, holds, releases, reversals) on any environment lacking the extension — would surface as a 500 error on the first financial action, with no graceful degradation.
- **Severity:** P0 (critical, financial, undetected by current tooling).
- **Fix:** tracked in `docs/PHASE2_2_BUG_FIX_LOG.md` — add `"ext-bcmath": "*"` to `composer.json`. The runtime extension itself cannot be installed in this sandbox (no package-manager network access — see §3); this is recorded as a deployment-environment requirement in `docs/PHASE2_2_DEPLOYMENT_CHECKLIST.md` carry-forward.

`ext-gmp` is also absent but is not referenced anywhere in the codebase — no action needed.

## 3. Network Access (re-verified, executed)

Identical blocker to Phase 2 and Phase 2.1, re-confirmed with fresh commands and additional evidence this cycle:

| Target | Command | Result |
|---|---|---|
| Packagist | `curl https://repo.packagist.org/packages.json` | `curl: (56) CONNECT tunnel failed, response 403` |
| npm registry | `curl https://registry.npmjs.org/` | `curl: (6) Could not resolve host` |
| PyPI | `curl https://pypi.org` | `curl: (6) Could not resolve host` |
| Ubuntu archive (apt) | `apt-get update` | `Could not resolve 'archive.ubuntu.com'` / `'security.ubuntu.com'` |
| Docker Hub | `docker pull hello-world` | `Forbidden` — agent-proxy log: `registry-1.docker.io:443 — connect_rejected... host is not allowed for this session` |

**New this cycle:** a local Docker daemon (`dockerd` v29.9.3) was found installed and **does start successfully** in this sandbox (`docker ps` works once the daemon is manually started). This was explored as a possible route to a real MySQL 8 server for stage 6 (Database Verification). However, `docker images` showed zero cached images, and `docker pull` of any image is blocked by the same egress policy as Packagist/npm (confirmed above). **This does not unblock database verification** — recorded here so the attempt and its outcome are on the record rather than silently skipped.

**Root cause (unchanged since Phase 2):** this sandbox's egress policy allow-lists only a small set of hosts (Anthropic API endpoints, and in principle `registry.npmjs.org`/`pypi.org` are listed in the proxy's `noProxy` config, but DNS resolution for them still fails in practice — the inconsistency was not investigated further as it is outside this project's control). `repo.packagist.org` and `registry-1.docker.io` are explicitly rejected by the proxy with HTTP 403 / `connect_rejected`.

## 4. Conclusion for this stage

- Static/local baseline: **PASS** — all CLI tools present except MySQL/MariaDB client+server.
- PHP extensions: **PASS** with one **P0 finding** (missing `bcmath`, see above).
- Local Redis: **PASS** (verified live in §"Queue, Redis & Scheduler" report — `PING`→`PONG`, `SET`/`GET` round-trip succeeded against a locally-started instance on port 6399).
- MySQL/MariaDB: **BLOCKED** — no client, no server, no package-manager access, no container-image access.
- Dependency installation (composer/npm): **BLOCKED** — see `docs/PHASE2_2_DEPENDENCY_REPORT.md`.

This is the same fundamental constraint that blocked Phase 2 and Phase 2.1 execution. It has not changed and this report does not pretend otherwise.
