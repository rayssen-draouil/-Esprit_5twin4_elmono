<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\SubmitProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\DecisionHistory;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjetDecision;
use App\Notifications\ProjetSubmitted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $query = Project::withCount('financements')->with('user');
        $query->when($request->filled('search'), fn (Builder $builder) => $builder->where('name', 'like', '%'.$request->string('search').'%'));
        $query->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->string('status')));
        $query->when($request->filled('type'), fn (Builder $builder) => $builder->where('type', $request->string('type')));

        $projects = $query->latest()->paginate(10)->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'statuses' => Project::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'types' => Project::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type'),
            'statistics' => [
                'total' => Project::count(),
                'planned' => Project::where('status', 'planned')->count(),
                'in_progress' => Project::where('status', 'in_progress')->count(),
                'completed' => Project::where('status', 'completed')->count(),
                'budget' => Project::sum('budget'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create(array_merge($request->validated(), [
            'validation_status' => 'draft',
        ]));

        $this->recordDecision($project, null, 'draft', 'submit', null);

        return redirect()->route('projects.show', $project)->with('success', 'Projet créé en brouillon.');
    }

    public function show(Project $project): View
    {
        $project->load(['user', 'financements.user', 'decisionHistories.user', 'validator']);
        $funded = (float) $project->financements->sum('amount');
        $budget = (float) $project->budget;

        return view('projects.show', [
            'project' => $project,
            'funded' => $funded,
            'remaining' => max(0, $budget - $funded),
            'fundingRate' => $budget > 0 ? min(100, round(($funded / $budget) * 100, 1)) : 0,
            'overBudget' => $funded > $budget,
        ]);
    }

    public function edit(Project $project): View
    {
        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Projet modifié avec succès.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Projet supprimé avec succès.');
    }

    /**
     * Soumettre un projet pour validation (Gestionnaire)
     */
    public function submit(SubmitProjectRequest $request, string $id): RedirectResponse
    {
        $project = Project::findOrFail($id);

        if (! $request->user() || $project->user_id !== $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez soumettre que vos propres projets.');
        }

        $oldStatus = $project->validation_status;

        if (! in_array($oldStatus, ['draft', 'changes_requested'], true)) {
            return back()->with('error', 'Ce projet ne peut pas être soumis dans son état actuel.');
        }

        $project->update([
            'validation_status' => 'pending_approval',
            'validation_comment' => null,
            'validated_by' => null,
            'validated_at' => null,
        ]);

        $comment = $request->string('comment');
        $this->recordDecision($project, $oldStatus, 'pending_approval', 'submit', $comment);

        User::where('role', 'admin')->each(fn ($admin) => $admin->notify(new ProjetSubmitted($project, $comment)));

        return redirect()->route('projects.show', $project)->with('success', 'Projet soumis pour validation.');
    }

    /**
     * Décision de l'Admin sur un projet (approuver, demander corrections, rejeter)
     */
    public function decide(DecideProjectRequest $request, string $id): RedirectResponse
    {
        $project = Project::findOrFail($id);

        if ($project->validation_status !== 'pending_approval') {
            return back()->with('error', 'Ce projet n\'est pas en attente de validation.');
        }

        if ($project->user_id === $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas valider votre propre projet.');
        }

        $oldStatus = $project->validation_status;
        $action = $request->string('action');
        $comment = $request->string('comment');
        $newStatus = $request->getNewStatus();

        $project->update([
            'validation_status' => $newStatus,
            'validation_comment' => $comment,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ]);

        $this->recordDecision($project, $oldStatus, $newStatus, $request->getActionType(), $comment);

        $project->user->notify(new ProjetDecision($project, $action, $comment, $request->user()->name));

        $message = match ($action) {
            'approve' => 'Projet approuvé.',
            'request_changes' => 'Demande de corrections envoyée.',
            'reject' => 'Projet rejeté.',
            default => 'Décision enregistrée.',
        };

        return redirect()->route('projects.show', $project)->with('success', $message);
    }

    /**
     * Enregistre une entrée dans l'historique des décisions
     */
    private function recordDecision(Project $project, ?string $oldStatus, string $newStatus, string $actionType, ?string $comment): void
    {
        DecisionHistory::create([
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'user_id' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'comment' => $comment,
            'action_type' => $actionType,
        ]);
    }
}
