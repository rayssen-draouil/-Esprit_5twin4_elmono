<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo de la zone (additif, NULLABLE).
 *
 * Une zone sans photo reste pleinement fonctionnelle : le front-office
 * affiche alors un visuel de remplacement. Aucune donnée existante n'est
 * modifiée ni supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            if (! Schema::hasColumn('zones', 'image_path')) {
                $table->string('image_path')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            if (Schema::hasColumn('zones', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
