<?php

namespace App\Notifications;

use App\Models\Financement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class FinancementDecision extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Financement $financement,
        public string $action, // approve, request_changes, reject
        public string $comment = '',
        public ?string $decidedBy = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $labels = [
            'approve' => 'approuvé',
            'request_changes' => 'demande de corrections',
            'reject' => 'rejeté',
        ];

        $label = $labels[$this->action] ?? 'décidé';

        return new DatabaseMessage([
            'title' => 'Décision sur votre financement',
            'message' => "Votre financement \"{$this->financement->source}\" a été {$label} sur le projet \"{$this->financement->project->name}\".".($this->comment ? " Commentaire : {$this->comment}" : ''),
            'type' => 'financement_decision',
            'action' => $this->action,
            'resource_type' => 'financement',
            'resource_id' => $this->financement->id,
            'resource_url' => route('financements.show', $this->financement),
            'actioned_by' => $this->decidedBy ?? 'Administrateur',
        ]);
    }
}
