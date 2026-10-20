<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'infrastructure_id' => ['required', 'integer', 'exists:infrastructures,id'],
            'reference_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9\-_]+$/i', 'unique:maintenances,reference_code'],
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'technician')],
            'team' => ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'type' => ['required', 'string', 'max:100'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'status' => ['required', 'in:planned,in_progress,completed,cancelled,reported'],
            'scheduled_at' => ['required', 'date'],
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'description' => ['nullable', 'string', 'max:3000'],
            'result' => ['nullable', 'string', 'max:3000'],
            'next_maintenance_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $status = $this->input('status');
            $scheduledAt = $this->input('scheduled_at');
            $startedAt = $this->input('started_at');
            $completedAt = $this->input('completed_at');
            $nextDate = $this->input('next_maintenance_date');

            // Logic 1: If creating a planned maintenance, the scheduled date must be today or in the future
            if ($status === 'planned' && $scheduledAt && strtotime($scheduledAt) < strtotime('today')) {
                $v->errors()->add(
                    'scheduled_at',
                    'Pour une maintenance au statut « Planifiée », la date prévisionnelle doit se situer aujourd’hui ou dans le futur.'
                );
            }

            // Logic 2: Completed status requires a technical result
            if ($status === 'completed' && empty($this->input('result'))) {
                $v->errors()->add(
                    'result',
                    'Le compte-rendu technique est obligatoire lorsqu’une opération de maintenance est marquée comme « Terminée ».'
                );
            }

            // Logic 3: Chronological coherence between start and completion
            if ($startedAt && $completedAt && strtotime($completedAt) < strtotime($startedAt)) {
                $v->errors()->add(
                    'completed_at',
                    'La date d’achèvement des travaux doit être égale ou postérieure à la date de début sur site (' . date('d/m/Y H:i', strtotime($startedAt)) . ').'
                );
            }

            // Logic 4: Next maintenance date must be after scheduled_at
            if ($scheduledAt && $nextDate && strtotime($nextDate) <= strtotime($scheduledAt)) {
                $v->errors()->add(
                    'next_maintenance_date',
                    'La prochaine échéance recommandée doit être postérieure à la date d’intervention planifiée.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'infrastructure_id.required' => 'Une infrastructure doit obligatoirement être sélectionnée pour cette intervention.',
            'infrastructure_id.exists' => 'L’infrastructure sélectionnée est introuvable.',
            'reference_code.regex' => 'Le code référence doit respecter le format standard (ex: MNT-2026-001).',
            'reference_code.unique' => 'Ce code de maintenance est déjà attribué à une autre opération.',
            'team.regex' => 'Le nom de l’équipe ne doit contenir que des lettres, espaces ou tirets.',
            'type.required' => 'Le type d’intervention (Préventive, Corrective, Inspection...) est obligatoire.',
            'priority.required' => 'Le niveau de priorité d’intervention doit être précisé.',
            'status.required' => 'Le statut opérationnel de la maintenance est requis.',
            'scheduled_at.required' => 'La date et l’heure planifiées sont obligatoires.',
            'started_at.before_or_equal' => 'La date de début d’intervention ne peut pas se situer dans le futur.',
            'completed_at.before_or_equal' => 'La date d’achèvement des travaux ne peut pas être postérieure à l’heure actuelle.',
            'cost.numeric' => 'Le montant du coût estimé ou engagé doit être une valeur numérique positive.',
            'cost.min' => 'Le coût financier ne peut pas être négatif.',
            'duration_hours.numeric' => 'La durée d’intervention doit être un nombre d’heures valide.',
            'duration_hours.min' => 'La durée minimale d’intervention est de 0.1 heure (6 minutes).',
            'duration_hours.max' => 'La durée d’intervention ne peut pas excéder 500 heures.',
            'next_maintenance_date.after_or_equal' => 'La date de prochaine maintenance préconisée doit être planifiée à aujourd’hui ou dans le futur.',
        ];
    }
}
