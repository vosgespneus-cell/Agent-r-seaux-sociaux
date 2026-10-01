import unittest
from runtime.adapters.whatsapp import WhatsAppAdapter
from runtime.adapters.phone import PhoneAdapter

class FakeWhatsApp:
    def __init__(self): self.sent=[]
    def health_check(self): return True
    def read_messages(self): return [{"id":"wa-1","payload":{"text":"bonjour"}}]
    def send_message(self, action): self.sent.append(action); return {"executed":True}

class FakePhone:
    def __init__(self): self.calls=[]
    def health_check(self): return True
    def read_calls(self): return [{"id":"call-1","missed":True,"payload":{}}]
    def callback(self, action): self.calls.append(action); return {"executed":True}

class ChannelSafetyTests(unittest.TestCase):
    def test_whatsapp_send_protected(self):
        backend=FakeWhatsApp(); adapter=WhatsAppAdapter(backend)
        self.assertFalse(adapter.execute_action({"type":"reply"})["executed"])
        self.assertEqual(backend.sent,[])

    def test_missed_call_is_high_priority(self):
        event=PhoneAdapter(FakePhone()).read_events()[0]
        self.assertEqual(event["priority"],"high")

    def test_outbound_call_protected(self):
        backend=FakePhone(); adapter=PhoneAdapter(backend)
        self.assertFalse(adapter.execute_action({"type":"callback"})["executed"])
        self.assertEqual(backend.calls,[])

if __name__=="__main__":
    unittest.main()
