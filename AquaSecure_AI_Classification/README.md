# AquaSecure — classification IA des signalements

Prototype pédagogique : TF-IDF + Logistic Regression.

## 1. Prérequis
- Python 3.10 ou plus récent
- Un terminal ouvert dans ce dossier

## 2. Installer
Windows :
```bash
py -m venv .venv
.venv\Scripts\activate
python -m pip install --upgrade pip
pip install -r requirements.txt
```

Si `py` ne fonctionne pas, essaie `python`.

## 3. Placer le dataset
Copie `AquaSecure_dataset_classification_signalements.csv` dans ce dossier, à côté de `train_model.py`.

Le CSV doit avoir les colonnes `description,categorie`.

## 4. Entraîner et évaluer
```bash
python train_model.py
```
Le script affiche l'accuracy, le rapport de classification et la matrice de confusion, puis crée `model_signalement.joblib`.

## 5. Tester une description
```bash
python predict.py
```
Exemple : `Depuis ce matin, aucune eau ne sort du robinet dans notre quartier.`

## 6. Étape suivante
Quand le prototype est validé, exposer le modèle via une API Flask (endpoint POST `/predict`) puis l'appeler depuis Laravel. Ne jamais laisser la prédiction IA remplacer la validation humaine.

## Limites
Les exemples fournis sont synthétiques et peu nombreux. Les scores sur ce jeu de démonstration ne garantissent pas les performances sur de vrais signalements. Collecter et faire annoter des exemples réels, variés et anonymisés avant toute utilisation opérationnelle.
