<?php

namespace App\Http\Requests;

use App\Models\Technicien;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckInterventionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['nullable', 'integer', Rule::in(Technicien::withTechnicianAccount()->pluck('id'))],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
        ];
    }

    public function messages(): array
    {
        return [
            'technician_id.in' => 'Le technicien sélectionné n’a pas de compte approuvé.',
        ];
    }
}
