frappe.listview_settings["MPIT Expense"] = {
    add_fields: ["project", "contract"],
    formatters: {
        expense_title(value, _field, doc) {
            const title = frappe.utils.escape_html(value || "");
            const context = doc.project ? `P: ${doc.project}` : doc.contract ? `C: ${doc.contract}` : "";

            if (!context) {
                return title;
            }

            return `${title} <span class="text-muted small">(${frappe.utils.escape_html(context)})</span>`;
        },
    },
};
