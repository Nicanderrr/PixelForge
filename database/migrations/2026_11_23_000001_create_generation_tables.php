<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('request_key');
            $table->unique(['user_id', 'request_key']);
            $table->string('provider_id')->nullable()->unique();
            $table->string('provider_key_fingerprint', 64);
            $table->text('prompt');
            $table->string('size', 20);
            $table->string('quality', 20);
            $table->json('reference_images');
            $table->json('request_payload');
            $table->string('status', 30)->default('queued')->index();
            $table->boolean('slot_reserved')->default(false);
            $table->decimal('estimated_provider_cost', 30, 18);
            $table->decimal('estimated_customer_price', 30, 18);
            $table->decimal('maximum_customer_price', 30, 18);
            $table->decimal('actual_provider_cost', 30, 18)->nullable();
            $table->decimal('actual_customer_price', 30, 18)->nullable();
            $table->json('estimated_rates');
            $table->json('rates')->nullable();
            $table->json('usage')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('submission_attempts')->default(0);
            $table->unsignedInteger('consecutive_errors')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('generation_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('generation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('provider_cost', 30, 18);
            $table->decimal('customer_charge', 30, 18);
            $table->json('usage');
            $table->json('rates');
            $table->timestamp('completed_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->json('payload');
            $table->string('body_hash', 64);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('generation_charges');
        Schema::dropIfExists('generations');
    }
};
