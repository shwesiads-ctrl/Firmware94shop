<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // IMEI/CARRIER/ICLOUD/FRP, SERVER/TOOLS/CARDS, RENT/SHARED, FILE/FIRMWARE
            $table->string('group');
            $table->foreignId('parent_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 4); // DECIMAL — monetary values are never floats
            $table->unsignedInteger('estimated_time_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_manual_review')->default(false);
            $table->timestamps();
        });

        Schema::create('service_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('key'); // e.g. imei, serial, model
            $table->string('label');
            $table->string('type')->default('text'); // text|number|select|file
            $table->json('options')->nullable(); // for select
            $table->boolean('required')->default(true);
            $table->string('validation_regex')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_service_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('supplier_service_ref'); // the ID/code the supplier uses for this service
            $table->decimal('supplier_cost', 12, 4);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['service_id', 'supplier_id'], 'service_supplier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_service_mappings');
        Schema::dropIfExists('service_fields');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
