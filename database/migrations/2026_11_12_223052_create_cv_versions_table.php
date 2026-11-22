<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->unsignedInteger('version_number');

            $table->json('snapshot');

            $table->timestamp('confirmed_at');

            $table->timestamps();

            $table->unique(
                ['cv_id', 'version_number'],
                'cv_versions_cv_id_version_number_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_versions');
    }
};
