<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,request_changes,reject'],
            'comment' => ['required_if:action,request_changes,reject', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function getActionType(): string
    {
        return match ((string) $this->string('action')) {
            'approve' => 'approve',
            'request_changes' => 'request_changes',
            'reject' => 'reject',
            default => 'unknown',
        };
    }

    public function getNewStatus(): string
    {
        return match ((string) $this->string('action')) {
            'approve' => 'approved',
            'request_changes' => 'changes_requested',
            'reject' => 'rejected',
            default => 'draft',
        };
    }
}
