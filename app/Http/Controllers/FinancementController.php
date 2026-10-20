<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancementRequest;
use App\Http\Requests\UpdateFinancementRequest;
use App\Models\Financement;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancementController extends Controller
{
    public function index(Request $request): View
    {
        $query = Financement::with('project');
        $query->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->string('status')));
        $query->when($request->filled('project_id'), fn (Builder $builder) => $builder->where('project_id', $request->integer('project_id')));
        $query->when($request->filled('source'), fn (Builder $builder) => $builder->where('source', 'like', '%' . $request->string('source') . '%'));

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
        $financement = Financement::create($request->validated());

        return redirect()->route('financements.index')->with('success', 'Financement ajouté avec succès.');
    }

    public function show(Financement $financement): View
    {
        return view('financements.show', ['financement' => $financement->load('project')]);
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
}
