<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_intelligence_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('completed'); // completed, failed, partial
            $table->json('summary_metrics')->nullable();
            $table->json('findings')->nullable();
            $table->integer('raw_risks_count')->default(0);
            $table->integer('overall_risk_score')->default(0);
            $table->string('overall_risk_level')->default('low'); // low, medium, high, critical
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_intelligence_scans');
    }
};
