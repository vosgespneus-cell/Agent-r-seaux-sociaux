import unittest
from runtime.watchdog import inspect_health, needs_attention

class WatchdogTests(unittest.TestCase):
    def test_healthy_machine_is_quiet(self):
        alerts=inspect_health({
            "status":"healthy","queue_depth":0,"waiting_human":0,"failed":0,
            "connectors":{"email":{"status":"healthy"}}
        })
        self.assertFalse(needs_attention(alerts))

    def test_dead_connector_is_reported(self):
        alerts=inspect_health({
            "status":"healthy","queue_depth":0,"waiting_human":0,"failed":0,
            "connectors":{"email":{"status":"down"}}
        })
        self.assertTrue(needs_attention(alerts))
        self.assertEqual(alerts[0]["connector"],"email")

    def test_queue_threshold_is_reported(self):
        alerts=inspect_health({
            "status":"healthy","queue_depth":30,"waiting_human":0,"failed":0
        })
        self.assertTrue(any(a["code"]=="queue_depth_threshold" for a in alerts))

if __name__=="__main__":
    unittest.main()
