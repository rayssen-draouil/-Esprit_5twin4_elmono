<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow de traitement des alertes (additif, NULLABLE).
 *
 * Une alerte sans ces colonnes reste parfaitement fonctionnelle :
 * elle est alors considérée comme "ouverte". Aucune donnée existante
 * n'est modifiée ni supprimée.
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

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            foreach (['resolved_at', 'acknowledged_at'] as $column) {
                if (Schema::hasColumn('alerts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
