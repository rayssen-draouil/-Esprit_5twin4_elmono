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
        ];
    }
}
