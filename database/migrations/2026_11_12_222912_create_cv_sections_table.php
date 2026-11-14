<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cv_id')
                ->constrained('cvs');

            $table->string('section_key', 100);

            $table->string('section_title', 255);

            $table->json('content');

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->boolean('is_visible')
                ->default(true);

            $table->timestamps();

            $table->unique(
                ['cv_id', 'section_key'],
                'cv_sections_cv_id_section_key_unique'
            );

            $table->index(
                ['cv_id', 'sort_order'],
                'cv_sections_cv_id_sort_order_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_sections');
    }
};
