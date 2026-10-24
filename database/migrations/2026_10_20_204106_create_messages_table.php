<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider', 30)->default('whatsapp');
            $table->string('provider_message_id', 191);
            $table->string('direction', 10)->default('inbound');
            $table->text('content');
            $table->string('status', 30)->default('received');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_message_id'],
                'messages_provider_message_unique'
            );

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};