<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->string('project_name', 255);

            $table->text('description')
                ->nullable();

            $table->string('project_url', 2048)
                ->nullable();

            $table->date('start_date')
                ->nullable();

            $table->date('end_date')
                ->nullable();

            $table->timestamps();

            $table->index('cv_id', 'cv_projects_cv_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_projects');
    }
};
