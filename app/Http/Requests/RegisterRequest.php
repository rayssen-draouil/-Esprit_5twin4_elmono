<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class RegisterRequest extends FormRequest
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
            'role' => ['required', Rule::in(['citizen', 'manager', 'technician'])],
            'speciality' => [
                new RequiredIf(fn (): bool => $this->input('role') === 'technician'),
                'nullable',
                'string',
                'max:255',
            ],
            'skills' => [
                new RequiredIf(fn (): bool => $this->input('role') === 'technician'),
                'nullable',
                'string',
                'max:2000',
            ],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'speciality.required' => 'Veuillez renseigner votre spécialité pour créer un compte technicien.',
            'speciality.string' => 'La spécialité doit être renseignée sous forme de texte.',
            'speciality.max' => 'La spécialité ne peut pas dépasser 255 caractères.',
            'skills.required' => 'Veuillez renseigner vos compétences pour créer un compte technicien.',
            'skills.string' => 'Les compétences doivent être renseignées sous forme de texte.',
            'skills.max' => 'La description des compétences ne peut pas dépasser 2 000 caractères.',
        ];
    }
}
