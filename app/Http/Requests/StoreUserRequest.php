<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'manager', 'gestionnaire', 'citizen', 'technician'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'speciality' => [
                new RequiredIf(fn (): bool => $this->input('role') === 'technician'),
                'nullable',
                'string',
                'max:255',
            ],
            'skills' => ['nullable', 'string', 'max:2000'],
            'password' => ['required', 'confirmed', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'speciality.required' => 'Veuillez renseigner la spécialité du technicien.',
            'speciality.string' => 'La spécialité doit être renseignée sous forme de texte.',
            'speciality.max' => 'La spécialité ne peut pas dépasser 255 caractères.',
        ];
    }
}
