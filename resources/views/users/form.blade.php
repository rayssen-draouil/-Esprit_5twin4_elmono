<form method="POST" action="{{ $action }}" class="contact-form user-form" novalidate>@csrf @if($method !== 'POST') @method($method) @endif
<label>Nom<input name="name" value="{{ old('name', $user?->name) }}" required></label>
<label>Email<input type="email" name="email" value="{{ old('email', $user?->email) }}" required></label>
@php($roleOptions = \App\Models\User::roleLabels())
@if($user?->role && ! array_key_exists($user->role, $roleOptions))
    @php($roleOptions = [$user->role => ucfirst($user->role)] + $roleOptions)
@endif
<label>Rôle<select name="role" id="user-role" required>@foreach($roleOptions as $value => $label)<option value="{{ $value }}" @selected(old('role', $user?->role) === $value)>{{ $label }}</option>@endforeach</select></label>
<label>Téléphone<input type="tel" name="phone" value="{{ old('phone', $user?->phone) }}"></label>
<label>Spécialité<input name="speciality" value="{{ old('speciality', $user?->speciality) }}"></label>
<label>Statut<select name="status"><option value="">—</option>@foreach(['available' => 'Disponible', 'busy' => 'Occupé', 'inactive' => 'Inactif'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $user?->status) === $value)>{{ $label }}</option>@endforeach</select></label>
<label>Mot de passe @if($user)<small>(laisser vide pour conserver)</small>@endif<input type="password" name="password" @required(!$user)></label>
<label>Confirmation<input type="password" name="password_confirmation" @required(!$user)></label>
<div><button class="button" type="submit">Enregistrer</button> <a class="button button-outline" href="{{ route('admin.users.index') }}">Annuler</a></div></form>
<script>
    (function () {
        var role = document.getElementById('user-role');
        if (!role) { return; }
        var toggle = function () {
            var technician = role.value === 'technician';
            role.form.querySelectorAll('input[name="phone"], input[name="speciality"], select[name="status"]').forEach(function (field) {
                field.closest('label').style.opacity = technician ? '1' : '0.5';
            });
        };
        role.addEventListener('change', toggle);
        toggle();
    })();
</script>
