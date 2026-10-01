import unittest
from runtime.boot import boot_mode, may_enable_real_actions

class BootTests(unittest.TestCase):
    def test_failed_component_forces_protected_mode(self):
        boot=boot_mode({"configuration":"pass","runtime":"pass","storage":"fail","supervisor":"pass"})
        self.assertEqual(boot["mode"],"protected")
        self.assertFalse(may_enable_real_actions(boot,True))

    def test_healthy_boot_still_starts_dry_run(self):
        boot=boot_mode({"configuration":"pass","runtime":"pass","storage":"pass","supervisor":"pass"})
        self.assertEqual(boot["mode"],"dry_run")
        self.assertFalse(may_enable_real_actions(boot,False))
        self.assertTrue(may_enable_real_actions(boot,True))

if __name__=="__main__":
    unittest.main()
