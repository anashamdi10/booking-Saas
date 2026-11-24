<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photographer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('access_type', 32)->default('private')->index();
            $table->string('access_email')->nullable();
            $table->string('access_password')->nullable();
            $table->uuid('share_token')->unique();
            $table->dateTime('expires_at')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->boolean('downloads_enabled')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['photographer_id', 'slug']);
            $table->index(['photographer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
