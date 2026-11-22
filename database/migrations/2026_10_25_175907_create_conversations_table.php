<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('state', 40)->default('welcome');
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(
                ['user_id', 'status'],
                'conversations_user_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
