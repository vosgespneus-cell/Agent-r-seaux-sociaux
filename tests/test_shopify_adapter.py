import unittest
from runtime.adapters.shopify import ShopifyAdapter

class FakeShopify:
    def __init__(self): self.writes=[]
    def health_check(self): return True
    def read_orders(self): return [{"id":"order-1","payload":{"total":"private"}}]
    def execute(self, action): self.writes.append(action); return {"executed":True}

class ShopifyAdapterTests(unittest.TestCase):
    def test_order_normalization(self):
        event=ShopifyAdapter(FakeShopify()).read_events()[0]
        self.assertEqual(event["source"],"shopify")
        self.assertEqual(event["type"],"order")

    def test_write_protected_by_default(self):
        backend=FakeShopify(); adapter=ShopifyAdapter(backend)
        result=adapter.execute_action({"type":"customer_update"})
        self.assertFalse(result["executed"])
        self.assertEqual(backend.writes,[])

if __name__=="__main__":
    unittest.main()
