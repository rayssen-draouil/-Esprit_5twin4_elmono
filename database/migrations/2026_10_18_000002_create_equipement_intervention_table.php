<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipement_intervention', function (Blueprint $table) {
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipement_id')->constrained('equipements')->cascadeOnDelete();
            $table->primary(['intervention_id', 'equipement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipement_intervention');
    }
};
