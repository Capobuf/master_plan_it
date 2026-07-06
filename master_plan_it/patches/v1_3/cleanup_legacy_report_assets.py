from __future__ import annotations

import frappe


REPORT_RENAMES = {
    "MPIT Overview": "MPIT Economic Position",
    "MPIT Monthly Plan": "MPIT Year End Forecast",
    "MPIT Renewals Window": "MPIT Renewals And Commitments",
}

CHART_RENAMES = {
    "MPIT Monthly Plan": "MPIT Year End Forecast",
    "MPIT Renewals Window (by Month)": "MPIT Renewals And Commitments (by Month)",
}

NUMBER_CARD_FIELDS = {
    "MPIT Forecast Total": "year_end_forecast",
    "MPIT Remaining Plafond": "remaining_or_over",
}


def execute() -> None:
    _cleanup_report_charts()
    _cleanup_number_cards()


def _cleanup_report_charts() -> None:
    for old_name, new_name in CHART_RENAMES.items():
        if frappe.db.exists("Dashboard Chart", old_name):
            if frappe.db.exists("Dashboard Chart", new_name):
                frappe.delete_doc("Dashboard Chart", old_name, force=1, ignore_permissions=True)
            else:
                frappe.rename_doc("Dashboard Chart", old_name, new_name, force=True, ignore_permissions=True)

        if frappe.db.exists("Dashboard Chart", new_name):
            report_name = frappe.db.get_value("Dashboard Chart", new_name, "report_name")
            frappe.db.set_value(
                "Dashboard Chart",
                new_name,
                {
                    "report_name": REPORT_RENAMES.get(report_name, report_name),
                    "chart_name": new_name,
                },
                update_modified=False,
            )

    for chart in frappe.get_all(
        "Dashboard Chart",
        filters={"report_name": ["in", list(REPORT_RENAMES)]},
        fields=["name", "report_name"],
        limit_page_length=100,
    ):
        frappe.db.set_value(
            "Dashboard Chart",
            chart.name,
            "report_name",
            REPORT_RENAMES[chart.report_name],
            update_modified=False,
        )


def _cleanup_number_cards() -> None:
    for card in frappe.get_all(
        "Number Card",
        filters={"report_name": ["in", list(REPORT_RENAMES.values()) + list(REPORT_RENAMES)]},
        fields=["name", "report_name"],
        limit_page_length=100,
    ):
        values = {"report_name": REPORT_RENAMES.get(card.report_name, card.report_name)}
        if card.name in NUMBER_CARD_FIELDS:
            values["report_field"] = NUMBER_CARD_FIELDS[card.name]
        frappe.db.set_value("Number Card", card.name, values, update_modified=False)
