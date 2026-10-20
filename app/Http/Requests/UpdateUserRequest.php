<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['required', Rule::in(['admin', 'manager', 'gestionnaire', 'citizen', 'technician'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'speciality' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ];
    }
}
