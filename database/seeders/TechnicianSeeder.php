<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TechnicianSeeder extends Seeder
{
    /**
     * Effectif technicien de référence (SONEDE & DGBGTH).
     *
     * Le technicien est un rôle applicatif : chaque entrée décrit un compte
     * utilisateur auquel le rôle `technician` est appliqué.
     *
     * @var array<string, array<string, string>>
     */
    public const TECHNICIANS = [
        'salem@gmail.com' => [
            'email' => 'salem@gmail.com',
            'name' => 'Salem',
            'phone' => '+216 71 123 456',
            'speciality' => 'Génie Civil & Auscultation Barrages (DGBGTH)',
            'status' => 'busy',
        ],
        'm.trabelsi@sonede.tn' => [
            'email' => 'm.trabelsi@sonede.tn',
            'name' => 'Ing. Mohamed Trabelsi',
            'phone' => '+216 71 888 101',
            'speciality' => 'Électromécanique & Groupes Motopompes (SONEDE)',
            'status' => 'available',
        ],
        'a.bensalem@agriculture.gov.tn' => [
            'email' => 'a.bensalem@agriculture.gov.tn',
            'name' => 'Ing. Ahmed Ben Salem',
            'phone' => '+216 78 450 200',
            'speciality' => 'Génie Civil & Auscultation Barrages (DGBGTH)',
            'status' => 'available',
        ],
        'y.mansouri@sonede.tn' => [
            'email' => 'y.mansouri@sonede.tn',
            'name' => 'Techn. Youssef Mansouri',
            'phone' => '+216 75 290 300',
            'speciality' => 'Dessalement d\'Eau de Mer & Osmose Inverse (SONEDE Zarat)',
            'status' => 'busy',
        ],
        'k.zouari@sonede.tn' => [
            'email' => 'k.zouari@sonede.tn',
            'name' => 'Techn. Karim Zouari',
            'phone' => '+216 72 285 400',
            'speciality' => 'Réseaux de Distribution & Détection de Fuites (SONEDE Cap Bon)',
            'status' => 'available',
        ],
        'f.khemiri@sonede.tn' => [
            'email' => 'f.khemiri@sonede.tn',
            'name' => 'Ing. Fatma Khemiri',
            'phone' => '+216 71 500 600',
            'speciality' => 'Automatisme SCADA & Télémétrie Qualité (SONEDE Ghdir El Golla)',
            'status' => 'available',
        ],
    ];

    public function run(): void
    {
        foreach (self::TECHNICIANS as $technician) {
            self::ensureTechnician($technician);
        }
    }

    /**
     * Crée le compte utilisateur technicien ou met à jour son profil métier.
     *
     * Un compte existant avec le même e-mail est réutilisé sans rétrograder
     * un rôle admin ou manager déjà attribué.
     */
    public static function ensureTechnician(array $attributes): User
    {
        $user = User::where('email', $attributes['email'])->first();

        if (! $user) {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Hash::make(User::DEFAULT_PASSWORD),
                'role' => 'technician',
                'email_verified_at' => now(),
            ]);
        } elseif ($user->role === 'citizen') {
            $user->update(['role' => 'technician']);
        }

        $user->fill([
            'phone' => $attributes['phone'] ?? null,
            'speciality' => $attributes['speciality'] ?? null,
            'status' => $attributes['status'] ?? 'available',
        ])->save();

        return $user;
    }
}
