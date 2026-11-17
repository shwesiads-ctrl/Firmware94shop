<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // F94-XXXXXXXX, shown to the customer
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('price_charged', 12, 4);
            $table->string('currency', 3)->default('USD');
            // pending -> reserved -> sent_to_supplier -> processing -> success|failed|unknown -> refunded
            $table->string('status')->default('pending');
            $table->string('idempotency_key')->unique();
            $table->string('placed_via')->default('storefront'); // storefront|reseller_rest_api|dhru_compatible_api
            $table->foreignId('api_credential_id')->nullable()->constrained('api_credentials')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->timestamps();
        });

        // Append-only audit trail of everything that happened to an order.
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('event'); // created|wallet_reserved|sent_to_supplier|supplier_accepted|supplier_rejected|status_updated|finalized|refunded
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('supplier_reference')->nullable(); // supplier's own order ID once accepted
            $table->string('status')->default('pending'); // pending|accepted|rejected|processing|success|failed|timeout|unknown
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_inputs');
        Schema::dropIfExists('orders');
    }
};
