<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->string('priority')->default('medium');
            $table->unsignedSmallInteger('scheduled_duration_minutes')->default(60);
            $table->text('work_plan')->nullable();
            $table->text('safety_precautions')->nullable();
            $table->index(['technician_id', 'status', 'scheduled_at'], 'interventions_schedule_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropIndex('interventions_schedule_lookup');
            $table->dropColumn([
                'priority',
                'scheduled_duration_minutes',
                'work_plan',
                'safety_precautions',
            ]);
        });
    }
};
