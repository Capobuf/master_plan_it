frappe.provide("master_plan_it.vat");

master_plan_it.vat.defaults_promise =
    master_plan_it.vat.defaults_promise ||
    frappe.call({ method: "master_plan_it.mpit_defaults.get_vat_defaults" }).then((r) => r.message || {});

async function maybe_autofill_next_renewal_date(frm) {
    if (!frm.doc.auto_renew || frm.doc.next_renewal_date || !frm.doc.end_date) {
        return;
    }
    await frm.set_value("next_renewal_date", frm.doc.end_date);
}

async function apply_term_defaults(cdt, cdn) {
    const defaults = await master_plan_it.vat.defaults_promise;
    const row = locals[cdt][cdn];

    if (!row.__vat_defaults_applied) {
        if (defaults.default_includes_vat !== undefined) {
            frappe.model.set_value(cdt, cdn, "amount_includes_vat", defaults.default_includes_vat ? 1 : 0);
        }
        if (defaults.default_vat_rate !== undefined && !row.vat_rate) {
            frappe.model.set_value(cdt, cdn, "vat_rate", defaults.default_vat_rate);
        }
        row.__vat_defaults_applied = true;
    }
}

frappe.ui.form.on("MPIT Contract", {
    refresh(frm) {
        maybe_autofill_next_renewal_date(frm);
        apply_sync_intro(frm);
    },

    auto_renew(frm) {
        maybe_autofill_next_renewal_date(frm);
    },
});

function apply_sync_intro(frm) {
    if (frm.is_new()) {
        frm.set_intro("");
        return;
    }
    frm.set_intro(__("Contract expenses are synchronized automatically after saving."), "blue");
}

frappe.ui.form.on("MPIT Contract Term", {
    terms_add(frm, cdt, cdn) {
        apply_term_defaults(cdt, cdn);
    },

    from_date(frm) {
        update_previous_term_end_dates(frm);
    },
});

function update_previous_term_end_dates(frm) {
    const terms = (frm.doc.terms || [])
        .filter((row) => row.from_date)
        .sort((a, b) => new Date(a.from_date) - new Date(b.from_date));

    if (terms.length < 2) {
        return;
    }

    for (let i = 0; i < terms.length - 1; i += 1) {
        const row = terms[i];
        if (row.to_date) {
            continue;
        }
        const next = terms[i + 1];
        frappe.model.set_value(row.doctype, row.name, "to_date", frappe.datetime.add_days(next.from_date, -1));
    }
}
