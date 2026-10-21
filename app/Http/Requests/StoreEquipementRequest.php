<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', 'unique:equipements,serial_number'],
            'status' => ['required', 'in:available,in_use,maintenance,out_of_service'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de l’équipement est obligatoire.',
            'name.max' => 'Le nom de l’équipement ne peut pas dépasser 255 caractères.',
            'type.required' => 'Le type de l’équipement est obligatoire.',
            'type.max' => 'Le type ne peut pas dépasser 255 caractères.',
            'serial_number.unique' => 'Ce numéro de série est déjà attribué à un autre équipement.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'description.max' => 'La description ne peut pas dépasser 5 000 caractères.',
            'image.image' => 'Le fichier téléversé doit être une image valide.',
            'image.mimes' => 'Formats d’image autorisés : JPEG, PNG, JPG ou WebP.',
            'image.max' => 'L’image ne doit pas dépasser 4 Mo.',
        ];
    }
}
