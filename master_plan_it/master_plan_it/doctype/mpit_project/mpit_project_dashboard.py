from frappe import _


def get_data(data=None):
    return {
        "fieldname": "project",
        "transactions": [
            {
                "label": _("Operations"),
                "items": ["MPIT Expense"],
            },
            {
                "label": _("Contracts"),
                "items": ["MPIT Contract"],
            },
        ],
    }
