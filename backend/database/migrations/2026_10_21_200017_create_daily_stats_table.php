<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('photographer_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('visits')->default(0);
            $table->unsignedBigInteger('unique_visitors')->default(0);
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('bookings_count')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['date', 'photographer_id'], 'daily_stats_unique_date_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
