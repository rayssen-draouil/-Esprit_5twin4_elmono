import unittest
from unittest.mock import patch

import app as classification_api


class ClassificationApiTest(unittest.TestCase):
    def setUp(self):
        self.client = classification_api.app.test_client()
        self.model = classification_api.model

    def tearDown(self):
        classification_api.model = self.model

    def test_health_reports_loaded_model(self):
        response = self.client.get("/health")

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json["status"], "ok")
        self.assertTrue(response.json["model_loaded"])

    def test_predict_accepts_description(self):
        response = self.client.post(
            "/predict", json={"description": "Une fuite d'eau sur la chaussée"}
        )

        self.assertEqual(response.status_code, 200)
        self.assertIn("categorie", response.json)
        self.assertIn("confidence", response.json)

    def test_predict_rejects_missing_description(self):
        response = self.client.post("/predict", json={})

        self.assertEqual(response.status_code, 400)

    def test_predict_rejects_empty_description(self):
        response = self.client.post("/predict", json={"description": "   "})

        self.assertEqual(response.status_code, 400)

    def test_predict_returns_server_error_when_model_is_unavailable(self):
        classification_api.model = None

        response = self.client.post("/predict", json={"description": "fuite"})

        self.assertEqual(response.status_code, 500)
        self.assertEqual(response.json["error"], "Modèle non chargé")

    def test_predict_handles_model_exception(self):
        with patch.object(classification_api.model, "predict", side_effect=RuntimeError("failure")):
            response = self.client.post("/predict", json={"description": "fuite"})

        self.assertEqual(response.status_code, 500)
        self.assertIn("Erreur lors de la prédiction", response.json["error"])


if __name__ == "__main__":
    unittest.main()
