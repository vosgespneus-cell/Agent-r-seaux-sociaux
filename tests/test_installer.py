import unittest
from installer.configurator import build_client_config, validate
from installer.diagnostic import readiness

class InstallerTests(unittest.TestCase):
    def test_default_install_starts_safe(self):
        config = build_client_config("Garage Test","garage-test",channels=["email","calendar"])
        self.assertTrue(validate(config)["ready"])
        self.assertTrue(config["automation"]["dry_run_first"])
        self.assertFalse(config["security"]["secrets_in_repository"])
        self.assertTrue(config["connectors"]["email"]["enabled"])
        self.assertFalse(config["connectors"]["shopify"]["enabled"])

    def test_unknown_connector_is_rejected(self):
        with self.assertRaises(ValueError):
            build_client_config("Test","test",channels=["unknown"])

    def test_critical_failure_blocks_activation(self):
        report = readiness({
            "configuration":"pass","runtime":"pass","storage":"pass",
            "supervisor":"fail","email":"warn"
        })
        self.assertFalse(report["ready"])
        self.assertIn("supervisor", report["critical_failures"])

if __name__ == "__main__":
    unittest.main()
