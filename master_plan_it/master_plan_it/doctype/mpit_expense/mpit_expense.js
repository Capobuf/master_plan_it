frappe.ui.form.on("MPIT Expense", {
    setup(frm) {
        set_link_queries(frm);
    },

    validate(frm) {
        update_all_expense_rows(frm);
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
    form_render(frm, cdt, cdn) {
        const did_update_amount = update_row_amount_from_unit_price(cdt, cdn);
        if (!did_update_amount) {
            update_row_vat_split(cdt, cdn);
        }
    },

    qty(frm, cdt, cdn) {
        const did_update_amount = update_row_amount_from_unit_price(cdt, cdn);
        if (!did_update_amount) {
            update_row_vat_split(cdt, cdn);
        }
    },

    unit_price(frm, cdt, cdn) {
        const did_update_amount = update_row_amount_from_unit_price(cdt, cdn);
        if (!did_update_amount) {
            update_row_vat_split(cdt, cdn);
        }
    },

    amount(frm, cdt, cdn) {
        update_row_vat_split(cdt, cdn);
    },

    amount_includes_vat(frm, cdt, cdn) {
        update_row_vat_split(cdt, cdn);
    },

    vat_rate(frm, cdt, cdn) {
        update_row_vat_split(cdt, cdn);
    },

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
    frm.toggle_display(
        ["section_context", "project", "contract", "section_funding", "uses_plafond", "plafond_expense", "is_extra"],
        ordinary
    );

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

function get_row_qty(row) {
    if (row.qty === null || row.qty === undefined || row.qty === "") {
        return to_float(1);
    }
    return to_float(row.qty);
}

// Client-side totals are UX feedback only; server-side validation recomputes the authoritative amounts.
function update_row_amount_from_unit_price(cdt, cdn) {
    const row = locals[cdt] && locals[cdt][cdn];
    if (!row) {
        return false;
    }

    const unit_price = to_float(row.unit_price || 0);
    if (!unit_price) {
        return false;
    }

    const amount = to_float(get_row_qty(row) * unit_price, 2);
    if (to_float(row.amount, 2) === amount) {
        return false;
    }

    frappe.model.set_value(cdt, cdn, "amount", amount);
    return true;
}

function update_row_vat_split(cdt, cdn) {
    const row = locals[cdt] && locals[cdt][cdn];
    if (!row) {
        return;
    }

    const amount = to_float(row.amount || 0);
    const rate = to_float(row.vat_rate || 0);
    const includes_vat = !!row.amount_includes_vat;

    let net = amount;
    let vat = amount * rate / 100;
    let gross = amount + vat;

    if (includes_vat && rate) {
        net = amount / (1 + rate / 100);
        vat = amount - net;
        gross = amount;
    }

    frappe.model.set_value(cdt, cdn, "amount_net", to_float(net, 2));
    frappe.model.set_value(cdt, cdn, "amount_vat", to_float(vat, 2));
    frappe.model.set_value(cdt, cdn, "amount_gross", to_float(gross, 2));
}

function update_all_expense_rows(frm) {
    const rows = frm.doc.rows || [];
    rows.forEach((row) => {
        if (!row || !row.doctype || !row.name) {
            return;
        }

        const did_update_amount = update_row_amount_from_unit_price(row.doctype, row.name);
        if (!did_update_amount) {
            update_row_vat_split(row.doctype, row.name);
        }
    });
}

function to_float(value, precision) {
    if (typeof flt === "function") {
        return flt(value, precision);
    }

    const parsed = Number(value);
    const numeric = Number.isFinite(parsed) ? parsed : 0;
    if (precision === undefined || precision === null) {
        return numeric;
    }

    const factor = 10 ** precision;
    return Math.round(numeric * factor) / factor;
}
