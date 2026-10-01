import unittest
from runtime.adapters.registry import dry_run_registry, health
from runtime.executor import execute

class AdapterTests(unittest.TestCase):
    def test_all_pilot_channels_have_dry_run_adapter(self):
        adapters = dry_run_registry()
        self.assertEqual(set(adapters), {"email","calendar","phone","whatsapp","shopify"})
        self.assertTrue(all(v["status"] == "healthy" for v in health(adapters).values()))

    def test_dry_run_has_no_external_execution(self):
        adapters = dry_run_registry(["email"])
        action = {
            "action_id": "A-1",
            "authorization": "automatic",
            "idempotency_key": "A-1-once",
            "type": "reply",
            "target": {"channel": "email"},
        }
        result = execute(action, adapters)
        self.assertEqual(result["status"], "completed")
        self.assertFalse(result["result"]["executed"])

if __name__ == "__main__":
    unittest.main()
