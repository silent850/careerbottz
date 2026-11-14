<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $cvTables = [
        'cv_personal_details',
        'cv_educations',
        'cv_experiences',
        'cv_skills',
        'cv_projects',
        'cv_certifications',
        'cv_languages',
        'cv_references',
        'cv_sections',
        'cv_versions',
    ];

    public function up(): void
    {
        foreach ($this->cvTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['cv_id']);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('cv_id')
                    ->references('id')
                    ->on('cvs')
                    ->cascadeOnDelete();
            });
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['active_cv_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('active_cv_id')
                ->references('id')
                ->on('cvs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach ($this->cvTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['cv_id']);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('cv_id')
                    ->references('id')
                    ->on('cvs');
            });
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['active_cv_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('active_cv_id')
                ->references('id')
                ->on('cvs');
        });
    }
};
