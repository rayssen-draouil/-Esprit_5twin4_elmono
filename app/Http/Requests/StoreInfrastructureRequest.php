<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInfrastructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255', 'regex:/^(?=.*[\p{L}])[\p{L}0-9\s\-\'.,()]+$/u'],
            'reference_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9\-_]+$/i', 'unique:infrastructures,reference_code'],
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'type' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:operational,maintenance,critical,offline,out_of_service'],
            'condition' => ['required', 'in:excellent,good,fair,poor,critical'],
            'criticality' => ['required', 'in:low,medium,high,vital'],
            'description' => ['nullable', 'string', 'max:3000'],
            'installation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'commissioning_date' => ['nullable', 'date', 'after_or_equal:installation_date'],
            'last_maintenance_date' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:installation_date'],
            'next_maintenance_date' => ['nullable', 'date', 'after_or_equal:today'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $lastMnt = $this->input('last_maintenance_date');
            $nextMnt = $this->input('next_maintenance_date');

            if ($lastMnt && $nextMnt && strtotime($nextMnt) <= strtotime($lastMnt)) {
                $v->errors()->add(
                    'next_maintenance_date',
                    'La date de prochaine maintenance doit être strictement postérieure à la date de dernière maintenance ('.date('d/m/Y', strtotime($lastMnt)).').'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de l’infrastructure est obligatoire.',
            'name.min' => 'Le nom de l’infrastructure doit contenir au moins :min caractères.',
            'name.regex' => 'Le nom de l’infrastructure doit contenir des lettres (seuls les lettres, chiffres, espaces et tirets sont acceptés).',
            'reference_code.regex' => 'Le code de référence ne peut contenir que des lettres, chiffres, tirets et underscores (ex: INF-2026-001).',
            'reference_code.unique' => 'Ce code de référence est déjà attribué à une autre infrastructure.',
            'zone_id.required' => 'Veuillez sélectionner un bassin ou une zone géographique d’implantation.',
            'zone_id.exists' => 'La zone géographique sélectionnée est introuvable.',
            'type.required' => 'Le type d’ouvrage hydraulique est obligatoire.',
            'status.required' => 'Le statut opérationnel actuel doit être précisé.',
            'condition.required' => 'L’état matériel de l’ouvrage doit être spécifié.',
            'criticality.required' => 'Le niveau de criticité pour l’approvisionnement en eau est requis.',
            'latitude.between' => 'La latitude GPS doit être comprise entre -90 et +90 degrés décimaux.',
            'longitude.between' => 'La longitude GPS doit être comprise entre -180 et +180 degrés décimaux.',
            'installation_date.before_or_equal' => 'La date d’installation ne peut pas se situer dans le futur.',
            'commissioning_date.after_or_equal' => 'La date de mise en service officielle doit être égale ou postérieure à la date d’installation.',
            'last_maintenance_date.before_or_equal' => 'La date de dernière maintenance passée ne peut pas être une date future.',
            'last_maintenance_date.after_or_equal' => 'La dernière maintenance ne peut pas précéder l’installation de l’ouvrage.',
            'next_maintenance_date.after_or_equal' => 'La prochaine échéance de maintenance doit être planifiée à aujourd’hui ou dans le futur.',
            'image.image' => 'Le fichier téléversé doit être une image valide.',
            'image.mimes' => 'Formats d’image autorisés : JPEG, PNG, JPG, WebP ou SVG.',
            'image.max' => 'La photo ne doit pas dépasser 4 Mo.',
        ];
    }
}
