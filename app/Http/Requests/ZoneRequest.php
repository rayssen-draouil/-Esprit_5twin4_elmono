<?php

namespace App\Http\Requests;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:3', 'max:255',
                Rule::unique('zones', 'name')->ignore($this->route('zone')),
            ],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'risk_level' => ['required', Rule::in(array_keys(Zone::RISK_LEVELS))],

            // Géolocalisation (additif, NULLABLE : les zones sans coordonnées restent valides).
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius_m' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'geojson' => ['nullable', 'array'],

            // Photo de la zone (facultative) : téléversée via le champ « image ».
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la zone est obligatoire.',
            'name.min' => 'Le nom doit contenir au moins 3 caractères.',
            'name.unique' => 'Une zone avec ce nom existe déjà.',
            'address.required' => "L'adresse est obligatoire.",
            'risk_level.required' => 'Choisissez un niveau de risque.',
            'risk_level.in' => 'Niveau de risque invalide.',

            'latitude.required_with' => 'La longitude est requise dès que la latitude est renseignée.',
            'longitude.required_with' => 'La latitude est requise dès que la longitude est renseignée.',
            'latitude.numeric' => 'La latitude doit être un nombre décimal.',
            'longitude.numeric' => 'La longitude doit être un nombre décimal.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
            'radius_m.integer' => 'Le rayon doit être un nombre entier (en mètres).',
            'radius_m.min' => 'Le rayon doit être d\'au moins 1 mètre.',
            'radius_m.max' => 'Le rayon ne peut pas dépasser 100 000 mètres.',
            'geojson.array' => 'Le contour géographique (GeoJSON) est invalide.',

            'image.image' => 'Le fichier doit être une image valide.',
            'image.mimes' => 'Formats acceptés : JPEG, PNG, JPG, WEBP ou SVG.',
            'image.max' => 'La photo ne peut pas dépasser 4 Mo.',
            'remove_image.boolean' => 'La demande de suppression de photo est invalide.',
        ];
    }
}
