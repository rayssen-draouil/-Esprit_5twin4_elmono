<form method="POST" action="{{ $action }}" class="contact-form user-form" novalidate>
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <label>Nom<input name="name" value="{{ old('name', $user?->name) }}" required></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $user?->email) }}" required></label>
    <label>Rôle
        <select name="role" required>
            @foreach(['admin'=>'Administrateur','manager'=>'Gestionnaire','gestionnaire'=>'Gestionnaire réseau','citizen'=>'Citoyen','technician'=>'Technicien'] as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user?->role) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Téléphone du technicien<input type="tel" name="phone" value="{{ old('phone', $user?->technicien?->phone) }}" maxlength="30"><small>Renseigné pour les comptes techniciens.</small></label>
    <label>Spécialité du technicien
        <input id="speciality" name="speciality" value="{{ old('speciality', $user?->technicien?->speciality) }}" maxlength="255" @error('speciality') aria-invalid="true" @enderror>
        <small>Obligatoire pour les comptes techniciens.</small>
        @error('speciality') <span role="alert">{{ $message }}</span> @enderror
    </label>
    <label>Compétences du technicien<textarea name="skills" rows="4" maxlength="2000">{{ old('skills', $user?->technicien?->skills) }}</textarea><small>Compétences complémentaires renseignées pour les comptes techniciens.</small></label>
    <label>Mot de passe @if($user)<small>(laisser vide pour conserver)</small>@endif<input type="password" name="password" @required(!$user)></label>
    <label>Confirmation<input type="password" name="password_confirmation" @required(!$user)></label>
    <div><button class="button" type="submit">Enregistrer</button> <a class="button button-outline" href="{{ route('admin.users.index') }}">Annuler</a></div>
</form>
