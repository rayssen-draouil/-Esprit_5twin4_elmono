<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenances', 'prolonged_at')) {
                $table->timestamp('prolonged_at')->nullable()->after('scheduled_at');
            }
            if (! Schema::hasColumn('maintenances', 'prolongation_days')) {
                $table->integer('prolongation_days')->default(0)->after('prolonged_at');
            }
            if (! Schema::hasColumn('maintenances', 'prolongation_reason')) {
                $table->text('prolongation_reason')->nullable()->after('prolongation_days');
            }
            if (! Schema::hasColumn('maintenances', 'prolongation_history')) {
                $table->json('prolongation_history')->nullable()->after('prolongation_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn([
                'prolonged_at',
                'prolongation_days',
                'prolongation_reason',
                'prolongation_history',
            ]);
        });
    }
};
