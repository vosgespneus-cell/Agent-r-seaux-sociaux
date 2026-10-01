import unittest
from runtime.adapters.email import EmailAdapter

class FakeEmailBackend:
    def __init__(self):
        self.sent = []
    def health_check(self):
        return True
    def read_messages(self):
        return [{"id":"mail-1","priority":"high","payload":{"subject":"Test"}}]
    def send_reply(self, action):
        self.sent.append(action)
        return {"message_id":"sent-1","executed":True}

class EmailAdapterTests(unittest.TestCase):
    def test_read_normalizes_email(self):
        adapter = EmailAdapter(FakeEmailBackend())
        event = adapter.read_events()[0]
        self.assertEqual(event["source"], "email")
        self.assertEqual(event["source_id"], "mail-1")
        self.assertEqual(event["type"], "message")

    def test_send_is_protected_by_default(self):
        backend = FakeEmailBackend()
        adapter = EmailAdapter(backend)
        result = adapter.execute_action({"type":"reply"})
        self.assertFalse(result["executed"])
        self.assertEqual(backend.sent, [])

    def test_private_runtime_can_enable_send(self):
        backend = FakeEmailBackend()
        adapter = EmailAdapter(backend, allow_send=True)
        result = adapter.execute_action({"type":"reply"})
        self.assertTrue(result["executed"])
        self.assertEqual(len(backend.sent), 1)

if __name__ == "__main__":
    unittest.main()
