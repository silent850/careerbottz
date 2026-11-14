<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cvs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users');

            $table->string('name', 255);

            $table->string('document_language', 10)
                ->default('en');

            $table->string('status', 30)
                ->default('draft');

            $table->string('template', 100)
                ->nullable();

            $table->timestamps();

            $table->index(
                ['user_id', 'status'],
                'cvs_user_id_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cvs');
    }
};
