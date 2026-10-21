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

        if (Schema::hasTable('equipements') && Schema::hasColumn('equipements', 'technicien_id')) {
            Schema::table('equipements', function (Blueprint $table) {
                $table->dropForeign(['technicien_id']);
                $table->unsignedBigInteger('technicien_id')->nullable()->change();
                $table->foreign('technicien_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        Schema::dropIfExists('techniciens');
    }

    public function down(): void
    {
        if (! Schema::hasTable('techniciens')) {
            Schema::create('techniciens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('speciality')->nullable();
                $table->text('skills')->nullable();
                $table->string('status')->default('available');
                $table->timestamps();
            });
        }
    }
};
