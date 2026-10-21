<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideFinancementRequest;
use App\Http\Requests\StoreFinancementRequest;
use App\Http\Requests\SubmitFinancementRequest;
use App\Http\Requests\UpdateFinancementRequest;
use App\Models\DecisionHistory;
use App\Models\Financement;
use App\Models\Project;
use App\Models\User;
use App\Notifications\FinancementDecision;
use App\Notifications\FinancementSubmitted;
use App\Services\FinancementCoverageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancementController extends Controller
{
    public function index(Request $request): View
    {
        $query = Financement::with(['project', 'user']);
        $query->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->string('status')));
        $query->when($request->filled('project_id'), fn (Builder $builder) => $builder->where('project_id', $request->integer('project_id')));
        $query->when($request->filled('source'), fn (Builder $builder) => $builder->where('source', 'like', '%'.$request->string('source').'%'));

        $financements = $query->latest()->paginate(10)->withQueryString();
        $amounts = Financement::query()->selectRaw('status, SUM(amount) as total')->groupBy('status')->pluck('total', 'status');

        return view('financements.index', [
            'financements' => $financements,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'statuses' => Financement::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'statistics' => [
                'total' => Financement::count(),
                'amount' => Financement::sum('amount'),
                'funded' => (float) ($amounts['funded'] ?? 0),
                'planned' => (float) ($amounts['planned'] ?? 0),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('financements.create', [
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'selectedProject' => $request->integer('project_id') ?: null,
        ]);
    }

    public function store(StoreFinancementRequest $request): RedirectResponse
    {
        $financement = $request->user()->financements()->create(array_merge($request->validated(), [
            'validation_status' => 'draft',
        ]));

        $this->recordDecision($financement, null, 'draft', 'submit', null);

        return redirect()->route('financements.show', $financement)->with('success', 'Financement créé en brouillon.');
    }

    public function show(Financement $financement): View
    {
        $financement->load(['project', 'user', 'decisionHistories.user', 'validator']);

        $coverage = FinancementCoverageService::calculate($financement);

        return view('financements.show', [
            'financement' => $financement,
            'coverage' => $coverage,
        ]);
    }

    public function edit(Financement $financement): View
    {
        return view('financements.edit', [
            'financement' => $financement,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateFinancementRequest $request, Financement $financement): RedirectResponse
    {
        $financement->update($request->validated());

        return redirect()->route('financements.show', $financement)->with('success', 'Financement modifié avec succès.');
    }

    public function destroy(Financement $financement): RedirectResponse
    {
        $financement->delete();

        return redirect()->route('financements.index')->with('success', 'Financement supprimé avec succès.');
    }

    /**
     * Soumettre un financement pour validation (Gestionnaire)
     */
    public function submit(SubmitFinancementRequest $request, string $id): RedirectResponse
    {
        $financement = Financement::findOrFail($id);

        if ($financement->user_id !== $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez soumettre que vos propres financements.');
        }

        $oldStatus = $financement->validation_status;

        if (! in_array($oldStatus, ['draft', 'changes_requested'], true)) {
            return back()->with('error', 'Ce financement ne peut pas être soumis dans son état actuel.');
        }

        $financement->update([
            'validation_status' => 'pending_approval',
            'validation_comment' => null,
            'validated_by' => null,
            'validated_at' => null,
            'resubmitted_after_changes_requested' => $oldStatus === 'changes_requested',
        ]);

        $comment = $request->string('comment');
        $this->recordDecision($financement, $oldStatus, 'pending_approval', 'submit', $comment);

        User::where('role', 'admin')->each(fn ($admin) => $admin->notify(new FinancementSubmitted($financement, $comment)));

        return redirect()->route('financements.show', $financement)->with('success', 'Financement soumis pour validation.');
    }

    /**
     * Décision de l'Admin sur un financement (approuver, demander corrections, rejeter)
     */
    public function decide(DecideFinancementRequest $request, string $id): RedirectResponse
    {
        $financement = Financement::findOrFail($id);

        if ($financement->validation_status !== 'pending_approval') {
            return back()->with('error', 'Ce financement n\'est pas en attente de validation.');
        }

        if ($financement->user_id === $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas valider votre propre financement.');
        }

        $oldStatus = $financement->validation_status;
        $action = $request->string('action');
        $comment = $request->string('comment');
        $newStatus = $request->getNewStatus();

        $financement->update([
            'validation_status' => $newStatus,
            'validation_comment' => $comment,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
            'resubmitted_after_changes_requested' => false,
        ]);

        $this->recordDecision($financement, $oldStatus, $newStatus, $request->getActionType(), $comment);

        $financement->user->notify(new FinancementDecision($financement, $action, $comment, $request->user()->name));

        $message = match ($action) {
            'approve' => 'Financement approuvé.',
            'request_changes' => 'Demande de corrections envoyée.',
            'reject' => 'Financement rejeté.',
            default => 'Décision enregistrée.',
        };

        return redirect()->route('financements.show', $financement)->with('success', $message);
    }

    /**
     * Enregistre une entrée dans l'historique des décisions
     */
    private function recordDecision(Financement $financement, ?string $oldStatus, string $newStatus, string $actionType, ?string $comment): void
    {
        DecisionHistory::create([
            'resource_type' => 'financement',
            'resource_id' => $financement->id,
            'user_id' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'comment' => $comment,
            'action_type' => $actionType,
        ]);
    }
}
