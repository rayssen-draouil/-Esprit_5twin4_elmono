from pathlib import Path
import pandas as pd
import joblib
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import classification_report, accuracy_score, confusion_matrix

BASE_DIR = Path(__file__).resolve().parent
DATASET = BASE_DIR / "AquaSecure_dataset_classification_signalements.csv"
MODEL_PATH = BASE_DIR / "model_signalement.joblib"

def main():
    if not DATASET.exists():
        raise FileNotFoundError(
            f"Dataset introuvable : {DATASET}\n"
            "Place le CSV dans le même dossier que train_model.py."
        )

    df = pd.read_csv(DATASET, encoding="utf-8")
    required = {"description", "categorie"}
    if not required.issubset(df.columns):
        raise ValueError("Le CSV doit contenir les colonnes description et categorie.")

    df = df.dropna(subset=["description", "categorie"])
    df["description"] = df["description"].astype(str).str.strip()
    df["categorie"] = df["categorie"].astype(str).str.strip()
    df = df[df["description"] != ""]

    X_train, X_test, y_train, y_test = train_test_split(
        df["description"],
        df["categorie"],
        test_size=0.20,
        random_state=42,
        stratify=df["categorie"],
    )

    model = Pipeline([
        ("tfidf", TfidfVectorizer(lowercase=True, strip_accents="unicode", ngram_range=(1, 2))),
        ("classifier", LogisticRegression(max_iter=1000, class_weight="balanced")),
    ])

    model.fit(X_train, y_train)
    predictions = model.predict(X_test)

    print(f"Nombre d'exemples : {len(df)}")
    print(f"Accuracy du test : {accuracy_score(y_test, predictions):.3f}")
    print("\nRapport de classification :")
    print(classification_report(y_test, predictions, zero_division=0))
    print("Matrice de confusion (ordre des catégories ci-dessous) :")
    print(model.named_steps["classifier"].classes_)
    print(confusion_matrix(y_test, predictions))

    joblib.dump(model, MODEL_PATH)
    print(f"\nModèle enregistré : {MODEL_PATH}")
    print("Note : le dataset actuel est synthétique ; ces métriques ne représentent pas forcément les performances réelles.")

if __name__ == "__main__":
    main()
