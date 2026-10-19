<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_webauthn_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('guard');
            $table->unsignedBigInteger('user_id');
            $table->string('credential_id')->unique();
            $table->text('record');
            $table->unsignedBigInteger('counter')->default(0);
            $table->string('name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['guard', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_webauthn_credentials');
    }
};
