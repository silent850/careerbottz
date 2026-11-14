<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('active_cv_id')
                ->nullable()
                ->constrained('cvs');

            $table->index(
                'active_cv_id',
                'conversations_active_cv_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['active_cv_id']);

            $table->dropIndex(
                'conversations_active_cv_id_index'
            );

            $table->dropColumn('active_cv_id');
        });
    }
};
