<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // dhru_classic | gsm_theme | fusion_pro | gsmss
            $table->string('driver'); // which Supplier Adapter class family handles this row
            $table->string('mode')->default('mock'); // mock|live
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0); // lower = tried first when multiple suppliers map the same service
            $table->timestamps();
        });

        Schema::create('supplier_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->text('encrypted_payload'); // Laravel encrypted cast holds url/username/api-key/pin etc, shape per adapter
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('capability'); // testConnection|getAccountInfo|getServices|placeOrder|getOrderStatus|getBulkOrderStatus|...
            $table->boolean('supported')->default(false);
            $table->timestamps();
            $table->unique(['supplier_id', 'capability']);
        });

        Schema::create('supplier_connection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('action'); // testConnection|placeOrder|getOrderStatus|...
            $table->string('outcome'); // success|failure|timeout|unknown
            $table->text('message')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_connection_logs');
        Schema::dropIfExists('supplier_capabilities');
        Schema::dropIfExists('supplier_credentials');
        Schema::dropIfExists('suppliers');
    }
};
