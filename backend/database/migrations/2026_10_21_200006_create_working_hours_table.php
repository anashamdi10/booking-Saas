<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photographer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('0 = Sunday ... 6 = Saturday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['photographer_id', 'weekday', 'start_time'], 'working_hours_unique_slot');
            $table->index(['photographer_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
