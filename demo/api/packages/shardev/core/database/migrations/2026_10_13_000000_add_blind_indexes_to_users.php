<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Example migration showing how to add blind index columns to a model.
 *
 * Run: php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Email blind index for exact-match searches on encrypted emails
            $table->string('email_blind_idx', 64)
                ->unique()
                ->nullable()
                ->after('email')
                ->comment('Blind index for email (HMAC-SHA256)');

            // Phone blind index
            $table->string('phone_blind_idx', 64)
                ->unique()
                ->nullable()
                ->after('phone')
                ->comment('Blind index for phone (HMAC-SHA256)');

            // Document number blind index
            $table->string('document_number_blind_idx', 64)
                ->unique()
                ->nullable()
                ->after('document_number')
                ->comment('Blind index for document number (HMAC-SHA256)');

            // Index for faster lookups
            $table->index('email_blind_idx');
            $table->index('phone_blind_idx');
            $table->index('document_number_blind_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_blind_idx_index');
            $table->dropIndex('users_phone_blind_idx_index');
            $table->dropIndex('users_document_number_blind_idx_index');

            $table->dropColumn([
                'email_blind_idx',
                'phone_blind_idx',
                'document_number_blind_idx',
            ]);
        });
    }
};