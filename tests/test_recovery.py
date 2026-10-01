import os, tempfile, unittest
from runtime.storage import connect
from runtime.recovery import recoverable_actions, mark_interrupted_for_review, already_completed

class RecoveryTests(unittest.TestCase):
    def setUp(self):
        fd,self.path=tempfile.mkstemp(); os.close(fd)
        self.db=connect(self.path)

    def tearDown(self):
        self.db.close(); os.unlink(self.path)

    def add(self, aid, key, status):
        self.db.execute(
            """INSERT INTO actions(action_id,event_id,type,payload,idempotency_key,
               authorization,status) VALUES(?,?,?,?,?,?,?)""",
            (aid,"E1","reply","{}",key,"automatic",status)
        )
        self.db.commit()

    def test_processing_action_is_recovered_as_retry(self):
        self.add("A1","once-1","processing")
        self.assertEqual(mark_interrupted_for_review(self.db),1)
        actions=recoverable_actions(self.db)
        self.assertEqual(actions[0]["status"],"retry_scheduled")

    def test_completed_action_is_not_recoverable(self):
        self.add("A1","once-1","completed")
        self.assertEqual(recoverable_actions(self.db),[])
        self.assertTrue(already_completed(self.db,"once-1"))

if __name__=="__main__":
    unittest.main()
