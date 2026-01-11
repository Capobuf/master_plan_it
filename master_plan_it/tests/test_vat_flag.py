import unittest
import frappe
from master_plan_it import tax


class TestVatFlag(unittest.TestCase):
    def test_split_from_gross(self):
        # 1220 gross at 22% => net 1000, vat 220
        net, vat, gross = tax.split_net_vat_gross(1220, 22, True)
        self.assertAlmostEqual(net, 1000.0, places=2)
        self.assertAlmostEqual(vat, 220.0, places=2)
        self.assertAlmostEqual(gross, 1220.0, places=2)

    def test_split_from_net(self):
        # 1000 net at 22% => gross 1220
        net, vat, gross = tax.split_net_vat_gross(1000, 22, False)
        self.assertAlmostEqual(net, 1000.0, places=2)
        self.assertAlmostEqual(vat, 220.0, places=2)
        self.assertAlmostEqual(gross, 1220.0, places=2)


if __name__ == '__main__':
    unittest.main()
