<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_personal_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->unique()
                ->constrained('cvs');

            $table->string('full_name', 255);

            $table->string('professional_title', 255)
                ->nullable();

            $table->string('email', 255)
                ->nullable();

            $table->string('phone', 30)
                ->nullable();

            $table->string('location', 255)
                ->nullable();

            $table->date('date_of_birth')
                ->nullable();

            $table->string('gender', 50)
                ->nullable();

            $table->string('marital_status', 50)
                ->nullable();

            $table->string('nationality', 100)
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_personal_details');
    }
};
