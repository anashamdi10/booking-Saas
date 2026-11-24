<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->char('visitor_hash', 64)->index();
            $table->char('session_hash', 64)->index();
            $table->foreignId('photographer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 512);
            $table->string('referer')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->dateTime('visited_at')->index();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['photographer_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
