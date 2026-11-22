<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inbound_message_id')
                ->unique()
                ->constrained('messages')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider', 30)->default('whatsapp');
            $table->string('idempotency_key', 64)->unique();
            $table->string('recipient_phone_e164', 16);
            $table->text('content');

            // pending, sending, sent, unknown, failed
            $table->string('status', 20)->default('pending');

            $table->string('provider_message_id', 191)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('unknown_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
