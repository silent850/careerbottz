<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_skills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->string('skill_name', 255);

            $table->string('category', 100)
                ->nullable();

            $table->timestamps();

            $table->index('cv_id', 'cv_skills_cv_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_skills');
    }
};
