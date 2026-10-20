<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes géographiques des zones (additif, NULLABLE).
 *
 * Une zone sans coordonnées reste parfaitement fonctionnelle :
 * les cartes affichent alors un état vide explicite.
 * Aucune donnée existante n'est modifiée ni supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            if (! Schema::hasColumn('zones', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('address');
            }

            if (! Schema::hasColumn('zones', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (! Schema::hasColumn('zones', 'radius_m')) {
                $table->unsignedInteger('radius_m')->nullable()->after('longitude');
            }

            if (! Schema::hasColumn('zones', 'geojson')) {
                $table->json('geojson')->nullable()->after('radius_m');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            foreach (['geojson', 'radius_m', 'longitude', 'latitude'] as $column) {
                if (Schema::hasColumn('zones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
