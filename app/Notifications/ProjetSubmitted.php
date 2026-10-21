<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class ProjetSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public string $comment = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Nouveau projet soumis pour validation',
            'message' => "Le projet \"{$this->project->name}\" a été soumis pour approbation.".($this->comment ? " Commentaire : {$this->comment}" : ''),
            'type' => 'project_submitted',
            'resource_type' => 'project',
            'resource_id' => $this->project->id,
            'resource_url' => route('projects.show', $this->project),
            'actioned_by' => $this->project->user?->name ?? 'Inconnu',
        ]);
    }
}
