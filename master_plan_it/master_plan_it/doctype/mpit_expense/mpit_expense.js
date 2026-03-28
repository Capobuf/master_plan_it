frappe.ui.form.on("MPIT Expense", {
    setup(frm) {
        set_link_queries(frm);
    },

    refresh(frm) {
        apply_kind_visibility(frm);
        apply_funding_rules(frm);
    },

    expense_kind(frm) {
        if (frm.doc.expense_kind === "Plafond") {
            frm.set_value("project", "");
            frm.set_value("contract", "");
            frm.set_value("uses_plafond", 0);
            frm.set_value("plafond_expense", "");
            frm.set_value("is_extra", 0);
        }
        apply_kind_visibility(frm);
        apply_funding_rules(frm);
    },

    uses_plafond(frm) {
        if (frm.doc.uses_plafond) {
            frm.set_value("is_extra", 0);
        }
        apply_funding_rules(frm);
    },

    is_extra(frm) {
        if (frm.doc.is_extra) {
            frm.set_value("uses_plafond", 0);
            frm.set_value("plafond_expense", "");
        }
        apply_funding_rules(frm);
    },

    year(frm) {
        set_link_queries(frm);
    },

    cost_center(frm) {
        set_link_queries(frm);
    },

    project(frm) {
        if (frm.doc.project && frm.doc.contract) {
            frm.set_value("contract", "");
        }
    },

    contract(frm) {
        if (frm.doc.contract && frm.doc.project) {
            frm.set_value("project", "");
        }
    },
});

frappe.ui.form.on("MPIT Expense Row", {
    spend_date(frm, cdt, cdn) {
        const row = locals[cdt][cdn];
        if (row.spend_date) {
            frappe.model.set_value(cdt, cdn, "start_date", null);
            frappe.model.set_value(cdt, cdn, "end_date", null);
            frappe.model.set_value(cdt, cdn, "distribution", null);
        }
    },

    start_date(frm, cdt, cdn) {
        const row = locals[cdt][cdn];
        if (row.start_date || row.end_date || row.distribution) {
            frappe.model.set_value(cdt, cdn, "spend_date", null);
        }
    },

    end_date(frm, cdt, cdn) {
        const row = locals[cdt][cdn];
        if (row.start_date || row.end_date || row.distribution) {
            frappe.model.set_value(cdt, cdn, "spend_date", null);
        }
    },

    distribution(frm, cdt, cdn) {
        const row = locals[cdt][cdn];
        if (row.start_date || row.end_date || row.distribution) {
            frappe.model.set_value(cdt, cdn, "spend_date", null);
        }
    },
});

function apply_kind_visibility(frm) {
    const ordinary = frm.doc.expense_kind === "Ordinary";
    frm.toggle_display(["project", "contract", "uses_plafond", "plafond_expense", "is_extra", "tab_classification"], ordinary);

    const grid = frm.fields_dict && frm.fields_dict.rows && frm.fields_dict.rows.grid;
    if (grid) {
        if (typeof grid.toggle_display === "function") {
            grid.toggle_display("row_phase", ordinary);
            grid.toggle_display("start_date", ordinary);
            grid.toggle_display("end_date", ordinary);
        } else if (typeof grid.update_docfield_property === "function") {
            grid.update_docfield_property("row_phase", "hidden", !ordinary);
            grid.update_docfield_property("start_date", "hidden", !ordinary);
            grid.update_docfield_property("end_date", "hidden", !ordinary);
        }
    }
}

function apply_funding_rules(frm) {
    const ordinary = frm.doc.expense_kind === "Ordinary";
    const uses_plafond = ordinary && !!frm.doc.uses_plafond;
    frm.toggle_display("plafond_expense", uses_plafond);
    frm.toggle_reqd("plafond_expense", uses_plafond);
}

function set_link_queries(frm) {
    frm.set_query("project", () => {
        const filters = {};
        if (frm.doc.cost_center) {
            filters.cost_center = frm.doc.cost_center;
        }
        return { filters };
    });

    frm.set_query("contract", () => {
        const filters = {};
        if (frm.doc.cost_center) {
            filters.cost_center = frm.doc.cost_center;
        }
        return { filters };
    });

    frm.set_query("plafond_expense", () => {
        const filters = {
            expense_kind: "Plafond",
            workflow_state: ["!=", "Cancelled"],
        };

        if (frm.doc.year) {
            filters.year = frm.doc.year;
        }
        if (frm.doc.cost_center) {
            filters.cost_center = frm.doc.cost_center;
        }

        return { filters };
    });

    frm.set_query("replaces_row_name", "rows", (doc, cdt, cdn) => {
        const row = locals[cdt] && locals[cdt][cdn] ? locals[cdt][cdn] : {};
        return {
            query: "master_plan_it.master_plan_it.doctype.mpit_expense.mpit_expense.get_expense_row_replacement_options",
            filters: {
                parent_expense: doc.name || "",
                current_row_name: row.name || "",
            },
        };
    });
}
