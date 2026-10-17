<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            if (! Schema::hasColumn('infrastructures', 'ai_health_analysis')) {
                $table->json('ai_health_analysis')->nullable()->after('image_path');
            }
            if (! Schema::hasColumn('infrastructures', 'ai_health_analyzed_at')) {
                $table->timestamp('ai_health_analyzed_at')->nullable()->after('ai_health_analysis');
            }
        });

        Schema::table('signalements', function (Blueprint $table) {
            if (! Schema::hasColumn('signalements', 'ai_analysis')) {
                $table->json('ai_analysis')->nullable()->after('reported_at');
            }
            if (! Schema::hasColumn('signalements', 'ai_status')) {
                $table->string('ai_status')->default('pending')->after('ai_analysis'); // pending, completed, failed
            }
            if (! Schema::hasColumn('signalements', 'ai_reviewed_at')) {
                $table->timestamp('ai_reviewed_at')->nullable()->after('ai_status');
            }
            if (! Schema::hasColumn('signalements', 'ai_reviewed_by')) {
                $table->foreignId('ai_reviewed_by')->nullable()->after('ai_reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('signalements', 'manager_category')) {
                $table->string('manager_category')->nullable()->after('ai_reviewed_by');
            }
            if (! Schema::hasColumn('signalements', 'manager_severity')) {
                $table->string('manager_severity')->nullable()->after('manager_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn(['ai_health_analysis', 'ai_health_analyzed_at']);
        });

        Schema::table('signalements', function (Blueprint $table) {
            $table->dropForeign(['ai_reviewed_by']);
            $table->dropColumn([
                'ai_analysis',
                'ai_status',
                'ai_reviewed_at',
                'ai_reviewed_by',
                'manager_category',
                'manager_severity',
            ]);
        });
    }
};
