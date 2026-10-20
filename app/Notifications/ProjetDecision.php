<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class ProjetDecision extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
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
            'title' => 'Décision sur votre projet',
            'message' => "Votre projet \"{$this->project->name}\" a été {$label}." . ($this->comment ? " Commentaire : {$this->comment}" : ''),
            'type' => 'project_decision',
            'action' => $this->action,
            'resource_type' => 'project',
            'resource_id' => $this->project->id,
            'resource_url' => route('projects.show', $this->project),
            'actioned_by' => $this->decidedBy ?? 'Administrateur',
        ]);
    }
}
