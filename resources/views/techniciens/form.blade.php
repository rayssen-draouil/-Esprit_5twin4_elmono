@csrf
<div class="form-grid">
    <label>Nom complet
        <input name="name" value="{{ old('name', $technician->name ?? '') }}" required>
        @error('name')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Email <span style="color: var(--coral);">*</span>
        <input type="email" name="email" value="{{ old('email', $technician->email ?? '') }}" required>
        <span class="form-hint">Identifiant de connexion du technicien.</span>
        @error('email')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Téléphone
        <input type="tel" name="phone" value="{{ old('phone', $technician->phone ?? '') }}">
        @error('phone')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Spécialité
        <input name="speciality" value="{{ old('speciality', $technician->speciality ?? '') }}">
        @error('speciality')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Statut
        <select name="status" required>
            @foreach(['available' => 'Disponible', 'busy' => 'Occupé', 'inactive' => 'Inactif'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $technician->status ?? 'available') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label>Mot de passe
        <input type="password" name="password" value="" autocomplete="new-password">
        <span class="form-hint">
            @if(isset($technician) && $technician->exists)
                Laissez vide pour conserver le mot de passe actuel.
            @else
                Mot de passe par défaut : {{ \App\Models\User::DEFAULT_PASSWORD }}
            @endif
        </span>
        @error('password')<small class="field-error">{{ $message }}</small>@enderror
    </label>
</div>
<div class="form-actions"><a class="button button-ghost" href="{{ route('techniciens.index') }}">Annuler</a><button class="button" type="submit">{{ $submitLabel }}</button></div>
