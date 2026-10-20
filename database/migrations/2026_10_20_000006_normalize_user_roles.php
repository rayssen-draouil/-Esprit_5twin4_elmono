<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalise les anciennes libellés de rôle vers les rôles canoniques
     * (admin, manager, technician, citizen) afin que l'administration ne
     * propose plus « gestionnaire réseau ».
     */
    public function up(): void
    {
        $aliases = [
            'gestionnaire' => 'manager',
            'citoyen' => 'citizen',
            'technicien' => 'technician',
        ];

        foreach ($aliases as $legacy => $canonical) {
            DB::table('users')->where('role', $legacy)->update(['role' => $canonical]);
        }

        DB::table('users')->whereNull('role')->update(['role' => 'citizen']);
    }

    public function down(): void
    {
        //
    }
};
