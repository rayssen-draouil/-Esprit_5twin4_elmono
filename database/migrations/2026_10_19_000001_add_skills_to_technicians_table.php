<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La table `techniciens` a été supprimée par
     * 2026_10_18_000000_align_technician_profiles_on_users_table : les
     * techniciens sont des utilisateurs (`users.role = 'technician'`) et
     * leurs compétences sont portées par `users.skills`. Sur une base à
     * jour, cette migration est donc sans effet (elle ne s'exécute que si
     * la table existe encore, par exemple sur une base historique).
     */
    public function up(): void
    {
        if (! Schema::hasTable('techniciens')) {
            return;
        }

        if (! Schema::hasColumn('techniciens', 'skills')) {
            Schema::table('techniciens', function (Blueprint $table) {
                $table->text('skills')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('techniciens') || ! Schema::hasColumn('techniciens', 'skills')) {
            return;
        }

        Schema::table('techniciens', function (Blueprint $table) {
            $table->dropColumn('skills');
        });
    }
};
