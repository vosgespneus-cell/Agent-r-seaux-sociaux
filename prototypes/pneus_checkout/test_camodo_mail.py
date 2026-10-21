import unittest
from camodo_mail import parse_selection


# Synthetic layout based on observed columns; no real customer data or link.
HTML = '''<table>
<tr><td></td><td colspan="3">PA HT</td><td colspan="3">PV TTC</td></tr>
<tr><th>Article(s)</th><th>Qté</th><th>Prix uni.</th><th>Total</th>
<th>TVA</th><th>Prix uni.</th><th>Total</th></tr>
<tr><td>205/55 R16 91V Example Tyre
<table><tr><td>Base de calcul HT</td><td>40,00 €</td></tr></table></td>
<td>4,00</td><td>39,00 €</td><td>156,00 €</td><td>20,00%</td>
<td>59,90 €</td><td>239,60 €</td></tr>
<tr><td>Prix total TTC</td><td>239,60 €</td></tr></table>
<a href="https://s-h-o-p24.pneus100.fr/shop/wk.html?PID=EXAMPLE_1&amp;Menge=4">Ajouter au panier</a>'''


class MailTests(unittest.TestCase):
    def test_retail_not_purchase_and_no_network(self):
        out = parse_selection(HTML)
        self.assertEqual(out['mail_unit_ttc_cents'], 5990)
        self.assertEqual(out['mail_total_ttc_cents'], 23960)
        self.assertEqual(out['quantity'], 4)
        self.assertEqual(out['reference'], 'EXAMPLE_1')
        self.assertEqual(out['title'], '205/55 R16 91V Example Tyre')
        self.assertFalse(out['payment_enabled'])
        self.assertTrue(out['supplier_recheck_required'])

    def test_unknown_format_and_amounts_fail_closed(self):
        for before, after in [('PV TTC', 'PV HT'), ('Qté','Quantité'),
                              ('59,90 €','59.90 €'), ('4,00','4,50'),
                              ('239,60 €</td></tr>','239,61 €</td></tr>')]:
            with self.subTest(before=before), self.assertRaises(ValueError):
                parse_selection(HTML.replace(before, after, 1))

    def test_reference_link_is_never_used_or_trusted_blindly(self):
        for before, after in [('https://','http://'),
                              ('s-h-o-p24.pneus100.fr','example.org'),
                              ('Menge=4','Menge=3'), ('PID=EXAMPLE_1','PID=a&PID=b'),
                              ('Menge=4','Menge=4&token=secret')]:
            with self.subTest(before=before), self.assertRaises(ValueError):
                parse_selection(HTML.replace(before,after))

    def test_multiple_items_or_missing_total_need_review(self):
        with self.assertRaises(ValueError):
            parse_selection(HTML+HTML)
        with self.assertRaises(ValueError):
            parse_selection(HTML.replace('Prix total TTC','Total inconnu'))


if __name__ == '__main__':
    unittest.main()
