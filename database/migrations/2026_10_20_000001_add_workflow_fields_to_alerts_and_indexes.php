<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs de suivi du cycle de vie des alertes (additif, NULLABLE) et index
 * de performance pour les listes back-office / front-office.
 *
 * Aucune colonne existante n'est modifiée et aucune donnée n'est supprimée :
 * les alertes déjà présentes restent valides (acknowledged_at / resolved_at NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (! Schema::hasColumn('alerts', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('read_at');
            }

            if (! Schema::hasColumn('alerts', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('acknowledged_at');
            }

            $table->index('resolved_at', 'alerts_resolved_at_index');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->index('ai_analyzed_at', 'zones_ai_analyzed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (Schema::hasColumn('alerts', 'acknowledged_at')) {
                $table->dropIndex('alerts_resolved_at_index');
                $table->dropColumn('acknowledged_at');
            }

            if (Schema::hasColumn('alerts', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }
        });

        Schema::table('zones', function (Blueprint $table) {
            if (Schema::hasColumn('zones', 'ai_analyzed_at')) {
                $table->dropIndex('zones_ai_analyzed_at_index');
            }
        });
    }
};
