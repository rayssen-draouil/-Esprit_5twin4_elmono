@extends('layouts.front')

@section('content')
    <section class="page-hero compact">
        <span class="eyebrow">CONTRIBUER À LA SÉCURITÉ</span>
        <h1>Création du<br><em>signalement.</em></h1>
        <p>Votre signalement permet à nos équipes d'intervenir rapidement.</p>
    </section>
    <section class="section contact-layout">
        <form class="contact-form validated-form" method="POST" action="{{ route('front.incidents.store') }}" enctype="multipart/form-data" novalidate id="form_incident">
            @csrf
            <label>Titre
                <input name="title" type="text" data-label="Titre" data-rules="required|min:3|max:255" value="{{ old('title') }}" placeholder="Ex. Fuite sur conduite principale">
                @error('title')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label>Description
                <textarea name="description" rows="6" data-label="Description" data-rules="required|min:10|max:5000" placeholder="Décrivez ce que vous avez observé..." id="incident-description">{{ old('description') }}</textarea>
                @error('description')<small class="field-error">{{ $message }}</small>@enderror
                <button type="button" class="button button-secondary ai-suggest-btn" id="ai-suggest-btn">
                    🤖 Suggérer le type avec l'IA
                </button>
                <div id="ai-suggestion" class="ai-suggestion" style="display: none;">
                    <span class="ai-suggestion-label">Suggestion IA :</span>
                    <span id="ai-suggestion-text" class="ai-suggestion-text"></span>
                    <small id="ai-confidence" class="ai-confidence"></small>
                </div>
            </label>
            <fieldset class="signalement-type">
                <legend>Type de signalement</legend>
                <label class="signalement-option" for="type-fuite">
                    <span class="signalement-icon" aria-hidden="true">💧</span>
                    <span>Fuite d'eau</span>
                    <input id="type-fuite" type="radio" name="type" value="Fuite d'eau" data-label="Type de signalement" data-rules="required" @checked(old('type') === "Fuite d'eau")>
                </label>
                <label class="signalement-option" for="type-contamination">
                    <span class="signalement-icon" aria-hidden="true">◉</span>
                    <span>Contamination</span>
                    <input id="type-contamination" type="radio" name="type" value="Contamination" @checked(old('type') === 'Contamination')>
                </label>
                <label class="signalement-option" for="type-secheresse">
                    <span class="signalement-icon" aria-hidden="true">◒</span>
                    <span>Coupure liée à la sécheresse</span>
                    <input id="type-secheresse" type="radio" name="type" value="Coupure liée à la sécheresse" @checked(old('type') === 'Coupure liée à la sécheresse')>
                </label>
                <label class="signalement-option" for="type-equipement">
                    <span class="signalement-icon" aria-hidden="true">⚙</span>
                    <span>Équipement hors ligne</span>
                    <input id="type-equipement" type="radio" name="type" value="Équipement hors ligne" @checked(old('type') === 'Équipement hors ligne')>
                </label>
            </fieldset>
            <label>Localisation
                <input name="location" type="text" data-label="Localisation" data-rules="required|min:3|max:255" value="{{ old('location') }}" placeholder="Adresse, commune ou infrastructure">
                @error('location')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <fieldset class="signalement-type signalement-severity">
                <legend>Niveau de gravité</legend>
                <label class="signalement-option" for="severity-low">
                    <span class="severity-dot severity-low" aria-hidden="true"></span>
                    <span>Faible</span>
                    <input id="severity-low" type="radio" name="priority" value="Faible" @checked(old('priority') === 'Faible')>
                </label>
                <label class="signalement-option" for="severity-medium">
                    <span class="severity-dot severity-medium" aria-hidden="true"></span>
                    <span>Moyenne</span>
                    <input id="severity-medium" type="radio" name="priority" value="Moyenne" @checked(old('priority', 'Moyenne') === 'Moyenne')>
                </label>
                <label class="signalement-option" for="severity-high">
                    <span class="severity-dot severity-high" aria-hidden="true"></span>
                    <span>Élevée</span>
                    <input id="severity-high" type="radio" name="priority" value="Élevée" @checked(old('priority') === 'Élevée')>
                </label>
                <label class="signalement-option" for="severity-critical">
                    <span class="severity-dot severity-critical" aria-hidden="true"></span>
                    <span>Critique</span>
                    <input id="severity-critical" type="radio" name="priority" value="Critique" @checked(old('priority') === 'Critique')>
                </label>
            </fieldset>
            <label>Date de l'incident
                <input name="incident_date" type="date" data-label="Date de l'incident" value="{{ old('incident_date', now()->format('Y-m-d')) }}">
                @error('incident_date')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <div class="photo-upload">
                <span class="form-label">Photo (facultatif)</span>
                <label class="file-upload-label" for="incident-photo">
                    <input id="incident-photo" name="photo" type="file" accept="image/*">
                    <span class="file-upload-design">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 5h-1.17l-1.24-1.38A1.99 1.99 0 0 0 15.1 3H8.9c-.57 0-1.12.24-1.49.62L6.17 5H5a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V8a3 3 0 0 0-3-3Zm-7 12a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                        <span>Ajoutez une photo de l'incident</span>
                        <span class="browse-button">Parcourir</span>
                        <small class="file-name">Aucun fichier sélectionné</small>
                    </span>
                </label>
            </div>
            <button class="button button-primary" type="submit">Envoyer le signalement ↗</button>
        </form>
        <div class="contact-info">
            <span class="eyebrow">BESOIN D'AIDE ?</span>
            <p>Pour une urgence immédiate, contactez votre responsable de site ou les services compétents.</p>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const descriptionInput = document.getElementById('incident-description');
            const suggestBtn = document.getElementById('ai-suggest-btn');
            const suggestionDiv = document.getElementById('ai-suggestion');
            const suggestionText = document.getElementById('ai-suggestion-text');
            const confidenceText = document.getElementById('ai-confidence');

            if (!descriptionInput || !suggestBtn) return;

            suggestBtn.addEventListener('click', async function() {
                const description = descriptionInput.value.trim();

                if (description.length < 5) {
                    alert('Veuillez entrer une description d\'au moins 5 caractères.');
                    return;
                }

                suggestBtn.disabled = true;
                suggestBtn.textContent = '⏳ Analyse en cours...';

                try {
                    const response = await fetch('/api/classification/predict', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({ description: description })
                    });

                    const data = await response.json();

                    if (data.success) {
                        suggestionText.textContent = data.data.categorie;
                        confidenceText.textContent = `Confiance : ${(data.data.confidence * 100).toFixed(1)}%`;
                        suggestionDiv.style.display = 'flex';

                        const typeMapping = {
                            'FUITE_EAU': 'Fuite d\'eau',
                            'CONTAMINATION_SUSPECTEE': 'Contamination',
                            'COUPURE_EAU': 'Coupure liée à la sécheresse',
                            'AUTRE': 'Fuite d\'eau'
                        };

                        const mappedType = typeMapping[data.data.categorie] || 'Fuite d\'eau';
                        const radioInput = document.querySelector(`input[name="type"][value="${mappedType}"]`);

                        if (radioInput) {
                            radioInput.checked = true;
                            radioInput.closest('.signalement-option').style.transform = 'scale(1.05)';
                            setTimeout(() => {
                                radioInput.closest('.signalement-option').style.transform = '';
                            }, 300);
                        }
                    } else {
                        alert('Erreur : ' + (data.error || 'Impossible de contacter le service de classification IA.'));
                        suggestionDiv.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Erreur AI:', error);
                    alert('Erreur de connexion au service de classification IA. Vérifiez que le serveur Flask est en cours d\'exécution.');
                    suggestionDiv.style.display = 'none';
                } finally {
                    suggestBtn.disabled = false;
                    suggestBtn.textContent = '🤖 Suggérer le type avec l\'IA';
                }
            });
        });
    </script>
@endpush
