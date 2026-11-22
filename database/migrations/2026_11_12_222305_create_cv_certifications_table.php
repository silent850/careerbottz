<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_certifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->string('certification_name', 255);

            $table->string('issuing_organization', 255)
                ->nullable();

            $table->date('issue_date')
                ->nullable();

            $table->date('expiry_date')
                ->nullable();

            $table->string('credential_id', 255)
                ->nullable();

            $table->string('credential_url', 2048)
                ->nullable();

            $table->timestamps();

            $table->index('cv_id', 'cv_certifications_cv_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_certifications');
    }
};
