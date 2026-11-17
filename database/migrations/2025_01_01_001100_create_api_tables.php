<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('public_key')->unique(); // sent as "username"/"key" in DHRU-compatible calls
            $table->string('secret_hash');           // hashed secret; raw secret is shown once on creation only
            $table->json('scopes')->nullable();       // e.g. ["orders.create","orders.read","wallet.read"]
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_ip_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_credential_id')->constrained('api_credentials')->cascadeOnDelete();
            $table->string('ip_cidr', 45); // single IP or CIDR, e.g. 203.0.113.5 or 203.0.113.0/24
            $table->timestamps();
        });

        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_credential_id')->nullable()->constrained('api_credentials')->nullOnDelete();
            $table->string('ip_address', 45);
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
        Schema::dropIfExists('api_ip_rules');
        Schema::dropIfExists('api_credentials');
    }
};
