import tempfile
import unittest
from pathlib import Path
from sessions import Sessions


class SessionsTest(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory()
        self.path=Path(self.tmp.name)/"sessions.sqlite"
        self.store=Sessions(self.path,b"test-only-secret-"*3)
        self.session=self.store.start("client@example.test",1000)

    def tearDown(self):
        self.store.close()
        self.tmp.cleanup()

    def mail(self, **changes):
        args=dict(message_id="test-mail-1",marker=self.session["comment_marker"],
                  customer_email="client@example.test",title="Pneu test",reference="TEST-001",
                  quantity=4,unit_cents=4370,received_at=1001,now=1002)
        args.update(changes)
        return self.store.receive(**args)

    def test_wait_receive_and_never_enable_payment(self):
        token=self.session["session_token"]
        self.assertEqual(self.store.status(token,1000)["state"],"waiting")
        self.assertEqual(self.mail(),"received")
        state=self.store.status(token,1002)
        self.assertEqual(state["reference"],"TEST-001")
        self.assertFalse(state["payment_enabled"])
        self.assertTrue(state["supplier_recheck_required"])

    def test_email_alone_does_not_disclose_selection(self):
        other=self.store.start("client@example.test",1000)
        self.mail()
        self.assertEqual(self.store.status(other["session_token"],1002)["state"],"waiting")
        self.assertEqual(self.store.status("unknown-token",1002)["state"],"expired")
        self.assertEqual(self.mail(customer_email="other@example.test"),"unmatched")

    def test_replay_idempotent_and_conflict_rejected(self):
        self.assertEqual(self.mail(),"received")
        self.assertEqual(self.mail(),"duplicate")
        self.assertEqual(self.mail(quantity=2),"conflict")
        self.assertEqual(self.store.status(self.session["session_token"],1002)["quantity"],4)

    def test_multiple_requests_are_not_silently_selected(self):
        self.mail()
        self.mail(message_id="test-mail-2",reference="TEST-002")
        self.assertEqual(self.store.status(self.session["session_token"],1002)["state"],"needs_review")

    def test_delay_expiry_old_mail_and_purge(self):
        token=self.session["session_token"]
        self.assertEqual(self.store.status(token,1090)["state"],"delayed")
        self.assertEqual(self.mail(received_at=999),"expired_or_old")
        self.assertEqual(self.mail(now=2800),"expired_or_old")
        self.store.purge(2800)
        self.assertEqual(self.store.status(token,2800)["state"],"expired")

    def test_no_raw_email_or_token_at_rest_and_private_permissions(self):
        self.store.db.commit()
        raw=self.path.read_bytes()
        self.assertNotIn(b"client@example.test",raw)
        self.assertNotIn(self.session["session_token"].encode(),raw)
        self.assertEqual(self.path.stat().st_mode & 0o777,0o600)

    def test_invalid_mail_amount_rejected(self):
        for value in [-1,0,True,"4370",4.2]:
            with self.assertRaises(ValueError): self.mail(unit_cents=value)


if __name__=="__main__": unittest.main()
