from frappe.tests.utils import FrappeTestCase
from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters

class TestDashboardUtils(FrappeTestCase):
	def test_normalize_list_filters(self):
		filters = [['MPIT Budget', 'year', '=', '2025']]
		normalized = normalize_dashboard_filters(filters)
		self.assertEqual(normalized, {'year': '2025'})

	def test_normalize_dict_filters(self):
		filters = {'year': '2024'}
		normalized = normalize_dashboard_filters(filters)
		self.assertEqual(normalized, {'year': '2024'})

	def test_normalize_json_string(self):
		import json
		filters = json.dumps([['MPIT Budget', 'cost_center', '=', 'CC-01']])
		normalized = normalize_dashboard_filters(filters)
		self.assertEqual(normalized, {'cost_center': 'CC-01'})

	def test_normalize_empty(self):
		self.assertEqual(normalize_dashboard_filters(None), {})
		self.assertEqual(normalize_dashboard_filters([]), {})

	def test_normalize_complex_list(self):
		# Filter that might look like standard list but has docstatus or extra fields
		filters = [
			['MPIT Budget', 'year', '=', '2023', 'extra'],
			['MPIT Budget', 'docstatus', '!=', 2] # Should skip docstatus if we only want kv pairs? 
			# Actually the implementation extracts 'docstatus' as key. 
			# Let's verify behavior. If we want global filters for charts, usually just fields.
		]
		normalized = normalize_dashboard_filters(filters)
		self.assertEqual(normalized.get('year'), '2023')
		self.assertEqual(normalized.get('docstatus'), 2)
