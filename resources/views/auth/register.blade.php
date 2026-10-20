@extends('layouts.auth', ['title' => 'Inscription', 'heading' => 'Créer un compte', 'subtitle' => 'Rejoignez la communauté AquaSecure.'])
@section('content')
<form method="POST" action="{{ route('register.store') }}" class="contact-form" novalidate>@csrf
<label>Nom<input type="text" name="name" value="{{ old('name') }}" required></label>
<label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
<label>Type de compte
    <select id="register-role" name="role" required>
        <option value="citizen" @selected(old('role', 'citizen') === 'citizen')>Citoyen</option>
        <option value="manager" @selected(old('role') === 'manager')>Gestionnaire</option>
        <option value="technician" @selected(old('role') === 'technician')>Technicien</option>
    </select>
</label>
<label id="technician-speciality-field" @if(old('role', 'citizen') !== 'technician') hidden @endif>
    Spécialité
    <input type="text" name="speciality" maxlength="255" placeholder="Ex. : hydraulique, électromécanique..." value="{{ old('speciality') }}" @required(old('role') === 'technician') @if($errors->has('speciality')) aria-invalid="true" class="is-invalid" @endif>
    <small>Indiquez votre domaine de spécialisation.</small>
    @error('speciality')
        <small class="field-error" role="alert">{{ $message }}</small>
    @enderror
</label>
<label id="technician-skills-field" @if(old('role', 'citizen') !== 'technician') hidden @endif>
    Compétences professionnelles
    <textarea name="skills" rows="4" maxlength="2000" placeholder="Ex. : détection de fuites, réparation de canalisations, électromécanique..." @required(old('role') === 'technician') @if($errors->has('skills')) aria-invalid="true" class="is-invalid" @endif>{{ old('skills') }}</textarea>
    <small>Décrivez vos compétences utiles aux interventions (2 000 caractères maximum). Cette information sera examinée par l’administrateur.</small>
    @error('skills')
        <small class="field-error" role="alert">{{ $message }}</small>
    @enderror
</label>
<label>Mot de passe<input type="password" name="password" required></label>
<label>Confirmer le mot de passe<input type="password" name="password_confirmation" required></label>
<button class="button" type="submit">Créer mon compte</button></form><p style="margin-bottom:0">Déjà inscrit ? <a class="text-link" href="{{ route('login') }}">Se connecter</a></p>
<script>
    (() => {
        const role = document.getElementById('register-role');
        const specialityField = document.getElementById('technician-speciality-field');
        const speciality = specialityField?.querySelector('[name="speciality"]');
        const skillsField = document.getElementById('technician-skills-field');
        const skills = skillsField?.querySelector('[name="skills"]');
        if (!role || !specialityField || !speciality || !skillsField || !skills) return;

        const toggleSkills = () => {
            const isTechnician = role.value === 'technician';
            specialityField.hidden = !isTechnician;
            speciality.required = isTechnician;
            skillsField.hidden = !isTechnician;
            skills.required = isTechnician;
        };

        role.addEventListener('change', toggleSkills);
        toggleSkills();
    })();
</script>
@endsection
