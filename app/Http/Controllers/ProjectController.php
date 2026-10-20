<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Financement;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $query = Project::withCount('financements')->with('financements');
        $query->when($request->filled('search'), fn (Builder $builder) => $builder->where('name', 'like', '%' . $request->string('search') . '%'));
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
        Project::create($request->validated());

        return redirect()->route('projects.index')->with('success', 'Projet créé avec succès.');
    }

    public function show(Project $project): View
    {
        $project->load('financements');
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
}
