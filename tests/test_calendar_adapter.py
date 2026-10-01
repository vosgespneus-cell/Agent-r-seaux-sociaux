import unittest
from runtime.adapters.calendar import CalendarAdapter

class FakeCalendarBackend:
    def __init__(self): self.created=[]
    def health_check(self): return True
    def read_events(self): return []
    def create_appointment(self, action):
        self.created.append(action)
        return {"calendar_reference":"cal-1","executed":True}
    def update_appointment(self, action):
        return {"calendar_reference":"cal-1","executed":True}

class CalendarAdapterTests(unittest.TestCase):
    def test_calendar_write_is_protected_by_default(self):
        backend=FakeCalendarBackend()
        adapter=CalendarAdapter(backend)
        result=adapter.execute_action({"type":"create_appointment"})
        self.assertFalse(result["executed"])
        self.assertEqual(backend.created,[])

    def test_calendar_write_can_be_enabled_privately(self):
        backend=FakeCalendarBackend()
        adapter=CalendarAdapter(backend,allow_write=True)
        result=adapter.execute_action({"type":"create_appointment"})
        self.assertTrue(result["executed"])
        self.assertEqual(len(backend.created),1)

if __name__=="__main__":
    unittest.main()
