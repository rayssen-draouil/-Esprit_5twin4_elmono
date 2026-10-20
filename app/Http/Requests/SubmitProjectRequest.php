<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'gestionnaire' || $this->user()->role === 'manager';
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function getValidationStatus(): string
    {
        return 'pending_approval';
    }

    public function getActionType(): string
    {
        return 'submit';
    }
}
