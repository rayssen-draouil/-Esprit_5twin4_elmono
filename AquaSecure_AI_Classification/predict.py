from pathlib import Path
import joblib

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "model_signalement.joblib"

def main():
    if not MODEL_PATH.exists():
        print("Modèle absent. Lance d'abord : python train_model.py")
        return

    model = joblib.load(MODEL_PATH)
    print("Classification des signalements AquaSecure (tape 'quitter' pour arrêter).")

    while True:
        description = input("\nDescription du signalement : ").strip()
        if description.lower() in {"quitter", "quit", "exit"}:
            break
        if not description:
            print("Saisis une description non vide.")
            continue

        categorie = model.predict([description])[0]
        probabilities = model.predict_proba([description])[0]
        confidence = float(max(probabilities))
        print(f"Catégorie suggérée : {categorie}")
        print(f"Confiance indicative : {confidence:.1%}")
        print("La catégorie doit être vérifiée par un gestionnaire, surtout avec ce petit dataset.")

if __name__ == "__main__":
    main()
