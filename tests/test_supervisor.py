import unittest
from src.supervisor import inspect


class SupervisorTests(unittest.TestCase):
    def test_completed_publication_requires_proof(self):
        task = {"id": "VP-1", "agent": "communication", "kind": "publication",
                "title": "Vidéo atelier", "source": "atelier", "status": "termine", "result": {}}
        self.assertFalse(inspect({"tasks": [task]})["ok"])
        task["result"] = {"post_url": "https://example.com/video"}
        self.assertTrue(inspect({"tasks": [task]})["ok"])

    def test_rejects_personal_data_and_duplicate_ids(self):
        task = {"id": "VP-1", "agent": "accueil", "kind": "demande",
                "title": "Demande reçue", "source": "contact", "status": "nouveau",
                "telephone_client": "0000000000"}
        self.assertFalse(inspect({"tasks": [task, task]})["ok"])


if __name__ == "__main__":
    unittest.main()
