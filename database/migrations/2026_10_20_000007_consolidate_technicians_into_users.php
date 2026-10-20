<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le technicien n'est plus une entité séparée : c'est un rôle applicatif
     * porté par la table `users`. Cette migration :
     *  1. ajoute les attributs métier du technicien sur `users` ;
     *  2. rapatrie les profils de la table `techniciens` ;
     *  3. répercute les affectations sur les identifiants `users` ;
     *  4. supprime la table `techniciens`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'speciality')) {
                $table->string('speciality')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('available')->after('speciality');
            }
        });

        if (! Schema::hasTable('techniciens')) {
            return;
        }

        $idMap = $this->migrateTechnicianProfiles();

        $this->dropTechnicianForeignKeys();
        $this->remapTechnicianIds($idMap);
        $this->createUserForeignKeys();

        Schema::dropIfExists('techniciens');
    }

    /**
     * Copie le profil métier des techniciens sur leur compte utilisateur.
     *
     * @return array<int, int> ancien identifiant technicien => identifiant utilisateur
     */
    protected function migrateTechnicianProfiles(): array
    {
        $idMap = [];

        DB::table('techniciens')->orderBy('id')->get()->each(function ($technician) use (&$idMap) {
            $user = null;

            if (! empty($technician->user_id)) {
                $user = DB::table('users')->where('id', $technician->user_id)->first();
            }

            if (! $user && ! empty($technician->email)) {
                $user = DB::table('users')->where('email', $technician->email)->first();
            }

            if ($user) {
                $userId = (int) $user->id;

                // Un compte « citizen » rattaché à un technicien devient technicien,
                // les rôles admin/manager ne sont jamais rétrogradés.
                $updates = [
                    'phone' => $technician->phone,
                    'speciality' => $technician->speciality,
                    'status' => $technician->status ?: 'available',
                ];

                if ($user->role === 'citizen') {
                    $updates['role'] = 'technician';
                }

                DB::table('users')->where('id', $userId)->update($updates);
            } else {
                $userId = (int) DB::table('users')->insertGetId([
                    'name' => $technician->name,
                    'email' => $technician->email,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password123'),
                    'role' => 'technician',
                    'phone' => $technician->phone,
                    'speciality' => $technician->speciality,
                    'status' => $technician->status ?: 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $idMap[(int) $technician->id] = $userId;
        });

        return $idMap;
    }

    /**
     * Supprime les clés étrangères pointant vers `techniciens`.
     */
    protected function dropTechnicianForeignKeys(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
        });

        Schema::table('interventions', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
        });

        Schema::table('maintenance_assignment_invitations', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
        });
    }

    /**
     * Réécrit les affectations avec les identifiants utilisateurs.
     *
     * @param array<int, int> $idMap
     */
    protected function remapTechnicianIds(array $idMap): void
    {
        if (empty($idMap)) {
            return;
        }

        $cases = [];
        $bindings = [];
        $placeholders = [];

        foreach ($idMap as $oldId => $newId) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $oldId;
            $bindings[] = $newId;
            $placeholders[] = '?';
        }

        $caseSql = 'CASE technician_id ' . implode(' ', $cases) . ' ELSE technician_id END';

        foreach (['maintenances', 'interventions', 'maintenance_assignment_invitations'] as $table) {
            DB::statement(
                "UPDATE {$table} SET technician_id = {$caseSql} WHERE technician_id IN (" . implode(', ', $placeholders) . ')',
                array_merge($bindings, array_keys($idMap))
            );
        }
    }

    /**
     * Recrée les clés étrangères sur `users`.
     */
    protected function createUserForeignKeys(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreign('technician_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('interventions', function (Blueprint $table) {
            $table->foreign('technician_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('maintenance_assignment_invitations', function (Blueprint $table) {
            $table->foreign('technician_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'speciality', 'status']);
        });
    }
};
