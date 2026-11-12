import unittest
from reconcile import reconcile
class TestReconcile(unittest.TestCase):
 def test_history_is_not_missed(self):
  x=reconcile([{'mission_id':'a','date':'2026-10-01','due':True,'driver_id':'d'}],[],[],[],[],'2026-11-12')[0]
  self.assertFalse(x['missing_declaration_alert']);self.assertNotIn('Réalisation déclarée',x['statuses'])
 def test_duplicate_not_confirmed(self):
  r={'mission_id':'a','date':'2026-11-12'}
  x=reconcile([r,r],[r],[r],[],[])[0];self.assertNotIn('Concordance confirmée',x['statuses'])
 def test_factor_not_customer_payment(self):
  r={'mission_id':'a','date':'2026-11-12'}
  x=reconcile([r],[r],[r],[{'mission_ids':['a'],'allocation_verified':True}],[{'mission_id':'a','bank_verified':True,'settles_in_full':True,'type':'factor_advance'}])[0]
  self.assertIn('Concordance confirmée',x['statuses']);self.assertNotIn('Réglée',x['statuses'])
 def test_amount_mismatch(self):
  x=reconcile([{'mission_id':'a','expected_ht':100}],[],[{'mission_id':'a','amount_ht':99}],[],[])[0];self.assertIn('Écart détecté',x['statuses'])
 def test_missing_amount_not_zero(self):
  x=reconcile([{'mission_id':'a','expected_ht':100}],[],[{'mission_id':'a'}],[],[])[0];self.assertNotIn('Écart détecté',x['statuses'])
if __name__=='__main__':unittest.main()
