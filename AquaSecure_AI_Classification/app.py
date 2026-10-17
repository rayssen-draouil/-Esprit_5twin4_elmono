from pathlib import Path
import joblib
from flask import Flask, request, jsonify
from flask_cors import CORS

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "model_signalement.joblib"

app = Flask(__name__)
CORS(app)

# Charger le modèle au démarrage
model = None
if MODEL_PATH.exists():
    model = joblib.load(MODEL_PATH)
    print(f"Modèle chargé depuis {MODEL_PATH}")
else:
    print("ERREUR: Modèle introuvable. Lance d'abord: python train_model.py")

@app.route('/health', methods=['GET'])
def health():
    """Endpoint de santé pour vérifier que l'API fonctionne"""
    return jsonify({
        'status': 'ok',
        'model_loaded': model is not None
    })

@app.route('/predict', methods=['POST'])
def predict():
    """
    API de classification des signalements
    
    Corps de la requête JSON:
    {
        "description": "L'eau s'écoule continuellement depuis la chaussée"
    }
    
    Réponse:
    {
        "categorie": "FUITE_EAU",
        "confidence": 0.85,
        "categories_probables": [
            {"categorie": "FUITE_EAU", "probabilite": 0.85},
            {"categorie": "AUTRE", "probabilite": 0.10},
            ...
        ]
    }
    """
    if model is None:
        return jsonify({
            'error': 'Modèle non chargé'
        }), 500
    
    data = request.get_json()
    
    if not data or 'description' not in data:
        return jsonify({
            'error': 'Le champ "description" est requis'
        }), 400
    
    description = data['description'].strip()
    
    if not description:
        return jsonify({
            'error': 'La description ne peut pas être vide'
        }), 400
    
    try:
        # Prédiction
        categorie = model.predict([description])[0]
        probabilities = model.predict_proba([description])[0]
        confidence = float(max(probabilities))
        
        # Récupérer toutes les catégories avec leurs probabilités
        classes = model.named_steps["classifier"].classes_
        categories_probables = [
            {
                'categorie': str(cat),
                'probabilite': float(prob)
            }
            for cat, prob in zip(classes, probabilities)
        ]
        # Trier par probabilité décroissante
        categories_probables.sort(key=lambda x: x['probabilite'], reverse=True)
        
        return jsonify({
            'categorie': str(categorie),
            'confidence': confidence,
            'categories_probables': categories_probables
        })
    
    except Exception as e:
        return jsonify({
            'error': f'Erreur lors de la prédiction: {str(e)}'
        }), 500

if __name__ == '__main__':
    print("Démarrage de l'API Flask de classification AquaSecure...")
    print("Endpoints disponibles:")
    print("  GET  /health - Vérifier le statut")
    print("  POST /predict - Classifier une description")
    app.run(host='0.0.0.0', port=5000, debug=True)
