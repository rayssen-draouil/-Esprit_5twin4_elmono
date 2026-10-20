<?php

namespace App\Notifications;

use App\Models\Financement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class FinancementSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Financement $financement,
        public string $comment = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Nouveau financement soumis pour validation',
            'message' => "Le financement \"{$this->financement->source}\" ({$this->financement->amount} €) a été soumis pour approbation sur le projet \"{$this->financement->project->name}\"." . ($this->comment ? " Commentaire : {$this->comment}" : ''),
            'type' => 'financement_submitted',
            'resource_type' => 'financement',
            'resource_id' => $this->financement->id,
            'resource_url' => route('financements.show', $this->financement),
            'actioned_by' => $this->financement->user?->name ?? 'Inconnu',
        ]);
    }
}
