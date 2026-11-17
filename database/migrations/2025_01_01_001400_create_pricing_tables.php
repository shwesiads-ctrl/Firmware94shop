<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_settings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // USD only in Phase 2, table kept extensible
            $table->string('symbol', 5);
            $table->decimal('exchange_rate_to_usd', 18, 8)->default(1);
            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('applies_to'); // all|role|reseller_group
            $table->string('role_or_group_slug')->nullable();
            $table->string('adjustment_type'); // markup_percent|markup_fixed|fixed_price
            $table->decimal('adjustment_value', 12, 4);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reseller_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('reseller_group_id')->nullable()->constrained('reseller_groups')->cascadeOnDelete();
            $table->foreignId('reseller_profile_id')->nullable()->constrained('reseller_profiles')->cascadeOnDelete();
            $table->decimal('price', 12, 4);
            $table->timestamps();

            // Either a group-level override or a specific reseller override, never both empty.
            $table->index(['service_id', 'reseller_group_id']);
            $table->index(['service_id', 'reseller_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_prices');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('currency_settings');
    }
};
