<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes d'intelligence artificielle pour les alertes.
 * Uniquement des colonnes NULLABLE : aucune donnée existante n'est affectée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (! Schema::hasColumn('alerts', 'ai_severity')) {
                $table->string('ai_severity', 20)->nullable()->after('severity');
            }
            if (! Schema::hasColumn('alerts', 'ai_explanation')) {
                $table->text('ai_explanation')->nullable()->after('ai_severity');
            }
            if (! Schema::hasColumn('alerts', 'ai_probable_cause')) {
                $table->text('ai_probable_cause')->nullable()->after('ai_explanation');
            }
            if (! Schema::hasColumn('alerts', 'ai_recommended_actions')) {
                $table->json('ai_recommended_actions')->nullable()->after('ai_probable_cause');
            }
            if (! Schema::hasColumn('alerts', 'ai_group_key')) {
                $table->string('ai_group_key', 64)->nullable()->after('ai_recommended_actions');
                $table->index('ai_group_key');
            }
            if (! Schema::hasColumn('alerts', 'ai_analyzed_at')) {
                $table->timestamp('ai_analyzed_at')->nullable()->after('ai_group_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (Schema::hasColumn('alerts', 'ai_group_key')) {
                $table->dropIndex(['ai_group_key']);
            }

            foreach (['ai_severity', 'ai_explanation', 'ai_probable_cause', 'ai_recommended_actions', 'ai_group_key', 'ai_analyzed_at'] as $column) {
                if (Schema::hasColumn('alerts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
