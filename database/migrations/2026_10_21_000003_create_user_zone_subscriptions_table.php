<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnements « Ma zone » des citoyens connectés.
 *
 * Nouvelle table (aucune donnée existante touchée). Les visiteurs non
 * connectés utilisent le stockage local du navigateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_zone_subscriptions')) {
            return;
        }

        Schema::create('user_zone_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_zone_subscriptions');
    }
};
