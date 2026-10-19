<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('guard');
            $table->string('email');
            $table->string('code');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['guard', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_otp_codes');
    }
};
