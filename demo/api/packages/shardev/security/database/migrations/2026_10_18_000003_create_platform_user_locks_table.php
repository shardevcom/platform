<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_user_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('guard');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('disabled_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['guard', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_user_locks');
    }
};
