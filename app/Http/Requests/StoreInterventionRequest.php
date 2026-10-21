<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInterventionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'scheduled_duration_minutes' => $this->input('scheduled_duration_minutes', 60),
            'priority' => $this->input('priority', 'medium'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'incident_id' => ['required', 'integer', 'exists:incidents,id'],
            'technician_id' => ['required', 'integer', Rule::in(User::technicians()->where('technician_approval_status', 'approved')->pluck('id'))],
            'team' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'scheduled_duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'work_plan' => ['nullable', 'string', 'max:5000'],
            'safety_precautions' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', 'in:planned,in_progress,completed,cancelled'],
            'result' => ['nullable', 'string', 'max:10000'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $validator->errors()->isNotEmpty()
                || ! $this->filled('technician_id')
                || ! $this->filled('scheduled_at')
                || ! $this->filled('scheduled_duration_minutes')
            ) {
                return;
            }

            $conflicts = app(\App\Services\InterventionSchedulingService::class)->conflicts(
                (int) $this->input('technician_id'),
                (string) $this->input('scheduled_at'),
                (int) $this->input('scheduled_duration_minutes'),
            );

            if ($conflicts->isNotEmpty()) {
                $validator->errors()->add(
                    'scheduled_at',
                    app(\App\Services\InterventionSchedulingService::class)->conflictMessage($conflicts)
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'incident_id.required' => 'Veuillez sélectionner un incident.',
            'incident_id.exists' => 'L’incident sélectionné n’existe pas.',
            'technician_id.required' => 'Il faut affecter un technicien à l’intervention.',
            'technician_id.in' => 'Le technicien sélectionné n’a pas de compte approuvé.',
            'team.required' => 'Le nom de l’équipe est obligatoire.',
            'team.max' => 'Le nom de l’équipe ne peut pas dépasser 255 caractères.',
            'scheduled_at.required' => 'La date prévue est obligatoire.',
            'scheduled_duration_minutes.required' => 'La durée prévue est obligatoire.',
            'scheduled_duration_minutes.integer' => 'La durée prévue doit être exprimée en minutes entières.',
            'scheduled_duration_minutes.min' => 'La durée minimale prévue est de 15 minutes.',
            'scheduled_duration_minutes.max' => 'La durée prévue ne peut pas dépasser 8 heures.',
            'priority.required' => 'Veuillez sélectionner une priorité.',
            'priority.in' => 'La priorité sélectionnée est invalide.',
            'work_plan.max' => 'Le plan de travail ne peut pas dépasser 5 000 caractères.',
            'safety_precautions.max' => 'Les précautions ne peuvent pas dépasser 3 000 caractères.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'result.max' => 'Le compte rendu ne peut pas dépasser 10 000 caractères.',
            'completed_at.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
        ];
    }
}
