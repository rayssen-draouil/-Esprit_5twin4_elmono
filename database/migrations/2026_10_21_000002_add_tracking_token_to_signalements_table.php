<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeton de suivi public d'un signalement citoyen (additif, NULLABLE).
 *
 * Permet à un citoyen de suivre son dossier via une référence opaque,
 * sans révéler l'identifiant interne ni permettre l'énumération des
 * dossiers. Les signalements existants restent valides (jeton nul).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            if (! Schema::hasColumn('signalements', 'tracking_token')) {
                $table->string('tracking_token', 40)->nullable()->unique()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('signalements', function (Blueprint $table) {
            if (Schema::hasColumn('signalements', 'tracking_token')) {
                $table->dropUnique(['tracking_token']);
                $table->dropColumn('tracking_token');
            }
        });
    }
};
