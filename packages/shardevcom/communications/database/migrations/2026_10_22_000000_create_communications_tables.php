<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_events', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('transactional')->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('communication_event_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('communication_events')
                ->cascadeOnDelete();
            $table->string('channel');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'channel']);
        });

        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('communication_events')
                ->cascadeOnDelete();
            $table->string('channel');
            $table->string('locale', 12)->default('en');
            $table->string('format')->default('html');
            $table->text('subject')->nullable();
            $table->longText('body');
            $table->json('metadata')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['event_id', 'channel', 'locale']);
        });

        Schema::create('communication_preferences', function (Blueprint $table): void {
            $table->id();
            $table->string('guard')->index();
            $table->string('user_id')->index();
            // '*' means every channel / every event.
            $table->string('channel')->default('*');
            $table->string('event_key')->default('*');
            $table->string('reason')->nullable();
            $table->timestamp('opted_out_at');
            $table->timestamps();

            $table->unique(['guard', 'user_id', 'channel', 'event_key']);
        });

        Schema::create('communication_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_key')->index();
            $table->foreignId('event_id')
                ->nullable()
                ->constrained('communication_events')
                ->nullOnDelete();
            $table->foreignId('template_id')
                ->nullable()
                ->constrained('communication_templates')
                ->nullOnDelete();
            $table->string('channel');
            $table->string('recipient_type');
            $table->string('recipient_id')->nullable();
            $table->string('recipient_guard')->nullable();
            $table->string('recipient_address')->nullable();
            $table->string('locale', 12);
            $table->string('format')->default('html');
            $table->string('status')->index();
            $table->text('subject')->nullable();
            $table->longText('body');
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->index(['event_key', 'channel', 'status']);
            $table->index(['recipient_type', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
        Schema::dropIfExists('communication_preferences');
        Schema::dropIfExists('communication_templates');
        Schema::dropIfExists('communication_event_channels');
        Schema::dropIfExists('communication_events');
    }
};
