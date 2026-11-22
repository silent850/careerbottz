<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_educations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->string('institution', 255);

            $table->string('qualification', 255);

            $table->string('field_of_study', 255)
                ->nullable();

            $table->date('start_date')
                ->nullable();

            $table->date('end_date')
                ->nullable();

            $table->string('grade', 100)
                ->nullable();

            $table->text('description')
                ->nullable();

            $table->timestamps();

            $table->index('cv_id', 'cv_educations_cv_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_educations');
    }
};
