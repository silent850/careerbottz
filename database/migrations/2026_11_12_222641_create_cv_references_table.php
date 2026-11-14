<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_references', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->string('full_name', 255);

            $table->string('professional_title', 255)
                ->nullable();

            $table->string('organization', 255)
                ->nullable();

            $table->string('email', 255)
                ->nullable();

            $table->string('phone', 30)
                ->nullable();

            $table->string('relationship', 255)
                ->nullable();

            $table->timestamps();

            $table->index('cv_id', 'cv_references_cv_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_references');
    }
};
