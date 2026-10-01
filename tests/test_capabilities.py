import unittest
from runtime.capabilities import resolve, enabled

class CapabilityTests(unittest.TestCase):
    def test_side_effects_are_off_by_default(self):
        config = resolve()
        self.assertFalse(enabled(config,"email","send"))
        self.assertFalse(enabled(config,"calendar","write"))
        self.assertFalse(enabled(config,"phone","outbound"))
        self.assertFalse(enabled(config,"whatsapp","send"))
        self.assertFalse(enabled(config,"shopify","write"))

    def test_one_capability_can_be_enabled_independently(self):
        config = resolve({"email":{"send":True}})
        self.assertTrue(enabled(config,"email","send"))
        self.assertFalse(enabled(config,"calendar","write"))

    def test_unknown_capability_is_rejected(self):
        with self.assertRaises(ValueError):
            resolve({"email":{"delete_everything":True}})

if __name__=="__main__":
    unittest.main()
