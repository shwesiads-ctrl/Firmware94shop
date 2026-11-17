<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 14, 4)->default(0);       // DECIMAL — never float
            $table->decimal('locked_balance', 14, 4)->default(0); // sum of active holds
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        // Wallet transactions are an immutable ledger: application code must
        // never UPDATE or DELETE a posted row. Corrections are posted as new
        // reversing entries (see WalletService::reverse()).
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type'); // deposit|debit|credit|refund|reversal|hold|release
            $table->decimal('amount', 14, 4); // positive magnitude; `type` conveys direction
            $table->decimal('balance_after', 14, 4);
            $table->string('reference_type')->nullable(); // e.g. App\Models\Order, App\Models\Deposit
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->decimal('amount', 14, 4);
            $table->string('status')->default('active'); // active|released|captured
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
