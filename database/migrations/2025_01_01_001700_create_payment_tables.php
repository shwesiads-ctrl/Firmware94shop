<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // usdt_trc20|usdt_bep20|manual
            $table->string('label');
            $table->string('network')->nullable(); // TRC20|BEP20|null for manual
            $table->boolean('is_active')->default(true);
            // Wallet address is never hardcoded in code; it's configured here
            // by an admin at runtime (or left null until they do).
            $table->string('receiving_address')->nullable();
            $table->timestamps();
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->decimal('amount', 14, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('network')->nullable();
            $table->string('transaction_hash')->nullable();
            $table->string('proof_path')->nullable(); // stored on the payment_proofs disk
            // pending -> approved|rejected. Never auto-approved from a tx
            // hash or screenshot alone — see docs/SECURITY.md.
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_id')->nullable()->constrained('deposits')->nullOnDelete();
            $table->string('source'); // which gateway/webhook, if any, called back
            $table->json('payload');
            $table->boolean('verified')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('payment_methods');
    }
};
