<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne le profil technicien sur la seule table `users`.
 *
 * Le technicien est un rôle applicatif (`users.role = 'technician'`) :
 * aucune table `techniciens` n'existe. Le profil métier (speciality,
 * status, skills) est porté par `users`, et les affectations
 * (`maintenances.technician_id`, `interventions.technician_id`,
 * `equipements.technicien_id`) pointent sur `users.id`.
 *
 * Cette migration :
 *  1. ajoute `users.skills` (compétences métier du technicien) ;
 *  2. recale la clé étrangère `equipements.technicien_id` sur `users` ;
 *  3. supprime la table `techniciens` (restaurée par erreur puis abandonnée).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'skills')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('skills')->nullable()->after('speciality');
            });
        }

    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'skills')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('skills');
            });
        }
    }
};
