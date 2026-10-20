@csrf
@include('partials.validation-summary', ['fieldLabels' => [
    'name' => 'Nom de l’équipement',
    'type' => 'Type',
    'serial_number' => 'Numéro de série',
    'status' => 'Statut',
    'description' => 'Description',
    'image' => 'Image de l’équipement',
]])
<div class="form-grid">
    <label>Nom de l’équipement
        <input id="name" name="name" value="{{ old('name', $equipement->name ?? '') }}" maxlength="255" required class="{{ $errors->has('name') ? 'is-invalid' : '' }}" data-label="Nom de l’équipement" data-rules="required|max:255">
        <div class="validation-feedback" data-field="name">@error('name') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Type
        <input id="type" name="type" value="{{ old('type', $equipement->type ?? '') }}" maxlength="255" required class="{{ $errors->has('type') ? 'is-invalid' : '' }}" data-label="Type" data-rules="required|max:255">
        <div class="validation-feedback" data-field="type">@error('type') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Numéro de série
        <input id="serial_number" name="serial_number" value="{{ old('serial_number', $equipement->serial_number ?? '') }}" maxlength="255" class="{{ $errors->has('serial_number') ? 'is-invalid' : '' }}" data-label="Numéro de série" data-rules="max:255">
        <div class="validation-feedback" data-field="serial_number">@error('serial_number') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label>Statut
        <select id="status" name="status" required class="{{ $errors->has('status') ? 'is-invalid' : '' }}" data-label="Statut" data-rules="required">
            @foreach(['available' => 'Disponible', 'in_use' => 'En service', 'maintenance' => 'En maintenance', 'out_of_service' => 'Hors service'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $equipement->status ?? 'available') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="validation-feedback" data-field="status">@error('status') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label class="form-full">Description
        <textarea id="description" name="description" rows="4" maxlength="5000" class="{{ $errors->has('description') ? 'is-invalid' : '' }}" data-label="Description" data-rules="max:5000">{{ old('description', $equipement->description ?? '') }}</textarea>
        <div class="validation-feedback" data-field="description">@error('description') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
    <label class="form-full">Image de l’équipement (facultative)
        @if(isset($equipement) && $equipement->image_path)
            <img src="{{ asset('storage/'.$equipement->image_path) }}" alt="Image actuelle de {{ $equipement->name }}" style="display:block;width:160px;height:120px;object-fit:cover;border-radius:8px;margin-bottom:10px">
        @endif
        <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="{{ $errors->has('image') ? 'is-invalid' : '' }}" data-label="Image de l’équipement">
        <span class="form-hint">Formats acceptés : JPEG, PNG ou WebP. Taille maximale : 4 Mo.</span>
        <div class="validation-feedback" data-field="image">@error('image') @include('partials.field-validation-error', ['message' => $message]) @enderror</div>
    </label>
</div>
<div class="form-actions"><a class="button button-ghost" href="{{ route('equipements.index') }}">Annuler</a><button class="button" type="submit">{{ $submitLabel }}</button></div>
