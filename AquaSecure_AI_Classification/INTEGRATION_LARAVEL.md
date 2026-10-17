# Intégration API Flask de Classification IA - AquaSecure

## Vue d'ensemble

Cette intégration permet aux citoyens de saisir une description de signalement et de recevoir une suggestion de catégorie (fuite, contamination, coupure d'eau) via une IA entraînée.

## Architecture

- **API Flask** : Expose le modèle de classification ML sur `http://127.0.0.1:5000`
- **Service Laravel** : `AIClassificationService` communique avec l'API Flask
- **Controller API** : `ClassificationController` expose les endpoints Laravel
- **Frontend** : Formulaire de signalement avec bouton "Suggérer le type avec l'IA"

## Démarrage du Service IA

### 1. Entraîner le modèle (si pas déjà fait)

```bash
cd AquaSecure_AI_Classification
python train_model.py
```

### 2. Lancer l'API Flask

```bash
cd AquaSecure_AI_Classification
python app.py
```

L'API sera accessible sur `http://127.0.0.1:5000`

## Endpoints API Flask

### GET /health
Vérifie si le service est disponible et le modèle chargé.

```json
{
  "status": "ok",
  "model_loaded": true
}
```

### POST /predict
Classifie une description de signalement.

**Requête :**
```json
{
  "description": "L'eau s'écoule continuellement depuis la chaussée"
}
```

**Réponse :**
```json
{
  "categorie": "FUITE_EAU",
  "confidence": 0.85,
  "categories_probables": [
    {"categorie": "FUITE_EAU", "probabilite": 0.85},
    {"categorie": "AUTRE", "probabilite": 0.10},
    ...
  ]
}
```

## Configuration Laravel

### Variables d'environnement

Ajouter dans `.env` :
```env
AI_CLASSIFICATION_URL=http://127.0.0.1:5000
```

### Service Laravel

Le service `AIClassificationService` est disponible via injection de dépendances :

```php
use App\Services\AIClassificationService;

public function store(Request $request, AIClassificationService $aiService)
{
    $result = $aiService->classify($request->description);
    $type = $aiService->mapCategoryToType($result['categorie']);
}
```

## Endpoints API Laravel

### POST /api/classification/predict
Classifie une description via le service IA.

**Requête :**
```json
{
  "description": "L'eau s'écoule continuellement depuis la chaussée"
}
```

**Réponse :**
```json
{
  "success": true,
  "data": {
    "categorie": "FUITE_EAU",
    "type": "fuite",
    "confidence": 0.85,
    "categories_probables": [...]
  }
}
```

### GET /api/classification/health
Vérifie la disponibilité du service IA.

## Frontend

Le formulaire de création d'incident (`resources/views/front/incidents/create.blade.php`) inclut :

1. **Bouton "Suggérer le type avec l'IA"** : Envoie la description à l'API
2. **Affichage de la suggestion** : Montre la catégorie suggérée et la confiance
3. **Sélection automatique** : Coche automatiquement le type correspondant

### Mapping des catégories

| Catégorie IA | Type Laravel |
|-------------|--------------|
| FUITE_EAU | Fuite d'eau |
| CONTAMINATION_SUSPECTEE | Contamination |
| COUPURE_EAU | Coupure liée à la sécheresse |
| AUTRE | Fuite d'eau (défaut) |

## Tests

### Tester l'API Flask directement

```bash
curl -X POST http://127.0.0.1:5000/predict \
  -H "Content-Type: application/json" \
  -d '{"description": "L'eau s'écoule continuellement depuis la chaussée"}'
```

### Tester l'API Laravel

```bash
curl -X POST http://127.0.0.1:8000/api/classification/predict \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: <token>" \
  -d '{"description": "L'eau s'écoule continuellement depuis la chaussée"}'
```

## Dépannage

### Le bouton IA ne fonctionne pas

1. Vérifiez que l'API Flask est lancée : `http://127.0.0.1:5000/health`
2. Vérifiez la console du navigateur pour les erreurs
3. Vérifiez que `AI_CLASSIFICATION_URL` est correct dans `.env`

### Erreur "Modèle non chargé"

1. Lancez `python train_model.py` dans `AquaSecure_AI_Classification`
2. Vérifiez que `model_signalement.joblib` existe

### CORS errors

L'API Flask utilise `flask-cors` pour autoriser les requêtes depuis Laravel.

## Améliorations futures

- [ ] Classification d'images (vision par ordinateur)
- [ ] Apprentissage continu avec les nouveaux signalements
- [ ] Interface d'administration pour valider/corriger les prédictions
- [ ] Dashboard de statistiques de performance du modèle
- [ ] Multi-langue pour les descriptions

## Sécurité

- **Validation humaine requise** : La suggestion IA ne doit jamais remplacer la validation par un gestionnaire
- **Dataset limité** : Le modèle actuel est entraîné sur des données synthétiques. Collectez des données réelles avant usage en production
- **Anonymisation** : Assurez-vous que les données d'entraînement sont anonymisées
