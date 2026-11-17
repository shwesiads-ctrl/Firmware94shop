<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('default_discount_percent', 5, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_group_id')->nullable()->constrained('reseller_groups')->nullOnDelete();
            $table->string('company_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('approval_status')->default('pending'); // pending|approved|rejected|suspended
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Explicit membership table even though reseller_profiles already has
        // a reseller_group_id FK, to support a reseller belonging to extra
        // (non-pricing) groups such as promotional cohorts, per master
        // prompt §5 Module B.
        Schema::create('reseller_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_group_id')->constrained('reseller_groups')->cascadeOnDelete();
            $table->foreignId('reseller_profile_id')->constrained('reseller_profiles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['reseller_group_id', 'reseller_profile_id'], 'reseller_group_members_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_group_members');
        Schema::dropIfExists('reseller_profiles');
        Schema::dropIfExists('reseller_groups');
    }
};
