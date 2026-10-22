<?php

namespace App\Http\Requests;

use App\Models\User;
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
            'role' => ['required', Rule::in(User::ASSIGNABLE_ROLES)],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'speciality' => [new RequiredIf(fn (): bool => $this->input('role') === 'technician'), 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['available', 'busy', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'speciality.required' => 'Veuillez renseigner la spécialité du technicien.',
        ];
    }
}
