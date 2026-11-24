<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('photographer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 32)->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 32)->default('pending')->index();
            $table->string('source', 32)->default('website');
            $table->text('notes')->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['photographer_id', 'start_at']);
            $table->index(['photographer_id', 'status']);
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
