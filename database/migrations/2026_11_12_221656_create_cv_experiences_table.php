<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_experiences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->string('employer', 255);

            $table->string('job_title', 255);

            $table->string('location', 255)
                ->nullable();

            $table->date('start_date')
                ->nullable();

            $table->date('end_date')
                ->nullable();

            $table->boolean('is_current')
                ->default(false);

            $table->text('responsibilities')
                ->nullable();

            $table->text('achievements')
                ->nullable();

            $table->timestamps();

            $table->index('cv_id', 'cv_experiences_cv_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_experiences');
    }
};
