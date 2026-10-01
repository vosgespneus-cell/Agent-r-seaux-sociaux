import unittest

from runtime.supervisor import decision
from runtime.legacy_bridge import public_task
from src.supervisor import inspect

class RuntimeBridgeTests(unittest.TestCase):
    def test_appointment_is_valid_public_task(self):
        event = {
            "event_id": "TEST-APPT-1",
            "source": "calendar",
            "type": "appointment",
            "priority": "high",
        }
        task = public_task(event, decision(event), "Rendez-vous a traiter")
        report = inspect({"tasks": [task]})
        self.assertTrue(report["ok"])
        self.assertEqual(task["agent"], "planning")

    def test_private_customer_fields_are_not_exported(self):
        event = {
            "event_id": "TEST-MSG-1",
            "source": "email",
            "type": "message",
            "payload": {
                "customer_name": "PRIVATE",
                "phone_number": "PRIVATE",
            },
        }
        task = public_task(event, decision(event))
        self.assertNotIn("payload", task)
        self.assertTrue(inspect({"tasks": [task]})["ok"])

if __name__ == "__main__":
    unittest.main()
