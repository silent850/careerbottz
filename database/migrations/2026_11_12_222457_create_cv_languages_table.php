<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_languages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->string('language', 100);

            $table->string('proficiency_level', 100)
                ->nullable();

            $table->timestamps();

            $table->index('cv_id', 'cv_languages_cv_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_languages');
    }
};
