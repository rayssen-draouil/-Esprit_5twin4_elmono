<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes d'intelligence artificielle pour les zones.
 * Uniquement des colonnes NULLABLE : aucune donnée existante n'est affectée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            if (! Schema::hasColumn('zones', 'ai_risk_score')) {
                $table->unsignedTinyInteger('ai_risk_score')->nullable()->after('risk_level');
            }
            if (! Schema::hasColumn('zones', 'ai_risk_level')) {
                $table->string('ai_risk_level', 20)->nullable()->after('ai_risk_score');
            }
            if (! Schema::hasColumn('zones', 'ai_summary')) {
                $table->text('ai_summary')->nullable()->after('ai_risk_level');
            }
            if (! Schema::hasColumn('zones', 'ai_recommendations')) {
                $table->json('ai_recommendations')->nullable()->after('ai_summary');
            }
            if (! Schema::hasColumn('zones', 'ai_analyzed_at')) {
                $table->timestamp('ai_analyzed_at')->nullable()->after('ai_recommendations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            foreach (['ai_risk_score', 'ai_risk_level', 'ai_summary', 'ai_recommendations', 'ai_analyzed_at'] as $column) {
                if (Schema::hasColumn('zones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
