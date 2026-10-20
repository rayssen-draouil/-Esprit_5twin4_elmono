@csrf
<div class="form-grid">
    <label>Nom du projet
        <input name="name" value="{{ old('name', $project->name ?? '') }}" data-label="Nom du projet" data-rules="required|min:3|letters_format">
        @error('name')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Type
        <input name="type" value="{{ old('type', $project->type ?? '') }}" data-label="Type" data-rules="required|min:2|letters_format">
        @error('type')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label class="form-full">Description
        <textarea name="description" rows="5" data-label="Description" data-rules="max:3000">{{ old('description', $project->description ?? '') }}</textarea>
        @error('description')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Budget
        <input type="number" name="budget" min="0" step="0.01" value="{{ old('budget', $project->budget ?? '') }}" data-label="Budget" data-rules="required|positive_number">
        @error('budget')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Statut
        <select name="status" data-label="Statut" data-rules="required">
            @foreach(['planned' => 'Planifié', 'in_progress' => 'En cours', 'completed' => 'Terminé', 'cancelled' => 'Annulé'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $project->status ?? 'planned') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Date de début
        <input type="date" name="start_date" value="{{ old('start_date', isset($project?->start_date) ? $project->start_date?->format('Y-m-d') : '') }}" data-label="Date de début">
        @error('start_date')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Date de fin
        <input type="date" name="end_date" value="{{ old('end_date', isset($project?->end_date) ? $project->end_date?->format('Y-m-d') : '') }}" data-label="Date de fin">
        @error('end_date')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Progression (%)
        <input type="number" name="progress" min="0" max="100" value="{{ old('progress', $project->progress ?? 0) }}" data-label="Progression" data-rules="required|number_between:0:100">
        @error('progress')<small class="field-error">{{ $message }}</small>@enderror
    </label>
</div>
<div class="form-actions"><a class="button button-ghost" href="{{ route('projects.index') }}">Annuler</a><button class="button" type="submit">{{ $submitLabel }}</button></div>
