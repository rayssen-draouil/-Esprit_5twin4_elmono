// AI Classification Service
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

        // Désactiver le bouton et afficher un état de chargement
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
                // Afficher la suggestion
                suggestionText.textContent = data.data.categorie;
                confidenceText.textContent = `Confiance : ${(data.data.confidence * 100).toFixed(1)}%`;
                suggestionDiv.style.display = 'block';

                // Sélectionner automatiquement le type correspondant
                const typeMapping = {
                    'FUITE_EAU': 'Fuite d\'eau',
                    'CONTAMINATION_SUSPECTEE': 'Contamination',
                    'COUPURE_EAU': 'Coupure liée à la sécheresse',
                    'AUTRE': 'Fuite d\'eau' // Par défaut
                };

                const mappedType = typeMapping[data.data.categorie] || 'Fuite d\'eau';
                const radioInput = document.querySelector(`input[name="type"][value="${mappedType}"]`);

                if (radioInput) {
                    radioInput.checked = true;
                    // Animation visuelle pour montrer la sélection
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
            // Réactiver le bouton
            suggestBtn.disabled = false;
            suggestBtn.textContent = '🤖 Suggérer le type avec l\'IA';
        }
    });
});
