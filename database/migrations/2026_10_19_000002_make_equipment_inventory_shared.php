<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipements', function (Blueprint $table) {
            $table->dropForeign(['technicien_id']);
            $table->unsignedBigInteger('technicien_id')->nullable()->change();
            $table->foreign('technicien_id')
                ->references('id')
                ->on('techniciens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipements', function (Blueprint $table) {
            $table->dropForeign(['technicien_id']);
            $table->foreign('technicien_id')
                ->references('id')
                ->on('techniciens')
                ->cascadeOnDelete();
        });
    }
};
