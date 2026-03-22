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
			['MPIT Budget', 'docstatus', '!=', 2],
		]
		normalized = normalize_dashboard_filters(filters)
		self.assertEqual(normalized.get('year'), '2023')
		self.assertEqual(normalized.get('docstatus'), 2)


class TestBudgetsByTypeChartSource(FrappeTestCase):
	"""Regression test: mpit_budgets_by_type must not filter MPIT Budget by cost_center.

	MPIT Budget does not have a cost_center field. Passing cost_center through
	to the ORM query would raise a FieldNotFound error.
	"""

	def test_get_data_with_cost_center_filter_does_not_crash(self):
		"""Passing a cost_center filter must not raise an error."""
		from master_plan_it.master_plan_it.dashboard_chart_source.mpit_budgets_by_type.mpit_budgets_by_type import (
			get_data,
		)
		# This previously crashed because MPIT Budget has no cost_center field.
		# The fix removes the dead cost_center branch from the chart source.
		result = get_data(filters={"cost_center": "CC-DOES-NOT-EXIST"})
		self.assertIn("labels", result)
		self.assertIn("datasets", result)

	def test_get_data_returns_valid_structure(self):
		"""Chart source must always return a non-empty labels+datasets structure."""
		from master_plan_it.master_plan_it.dashboard_chart_source.mpit_budgets_by_type.mpit_budgets_by_type import (
			get_data,
		)
		result = get_data(filters={})
		self.assertIn("labels", result)
		self.assertIn("datasets", result)
		self.assertGreater(len(result["labels"]), 0)
		self.assertGreater(sum(result["datasets"][0]["values"]), 0)
