/**
 * UI creation coverage for the primary Master Plan IT documents.
 *
 * These tests deliberately open real Frappe Desk forms and save through the
 * browser-side form controller. That exercises the user-facing route, form
 * boot, client scripts, child tables, server validation, and autoname path.
 *
 * Test data is isolated with a run id and removed after each test. Cleanup is
 * API-based because it must be reliable even when a UI assertion fails midway.
 */

const created = {
  expenses: [],
  contracts: [],
  projects: [],
  vendors: [],
  costCenters: [],
};

function runId() {
  return `CY-${Date.now()}-${Cypress._.random(1000, 9999)}`;
}

function track(kind, name) {
  if (name) {
    created[kind].push(name);
  }
  return name;
}

function createVendor(name) {
  return cy
    .frappePost("/api/resource/MPIT Vendor", {
      doctype: "MPIT Vendor",
      vendor_name: name,
      is_active: 1,
    })
    .then((res) => track("vendors", res.body?.data?.name));
}

function createCostCenter(name) {
  return cy
    .frappePost("/api/resource/MPIT Cost Center", {
      doctype: "MPIT Cost Center",
      cost_center_name: name,
      parent_mpit_cost_center: "All Cost Centers",
      is_group: 0,
    })
    .then((res) => track("costCenters", res.body?.data?.name));
}

function findGeneratedExpensesForContract(contractName) {
  const filters = encodeURIComponent(JSON.stringify([["contract", "=", contractName]]));
  return cy
    .request(`/api/resource/MPIT Expense?fields=["name"]&filters=${filters}&limit_page_length=50`)
    .then((res) => {
      (res.body?.data || []).forEach((row) => track("expenses", row.name));
    });
}

function cleanupCreatedDocuments() {
  // Generated contract expenses must go first, otherwise contract deletion can
  // leave economic rows behind and make later tests observe polluted totals.
  cy.wrap([...created.contracts]).each((contractName) => {
    findGeneratedExpensesForContract(contractName);
  });

  cy.then(() => {
    const steps = [
      ["MPIT Expense", "expenses"],
      ["MPIT Contract", "contracts"],
      ["MPIT Project", "projects"],
      ["MPIT Vendor", "vendors"],
      ["MPIT Cost Center", "costCenters"],
    ];

    steps.forEach(([doctype, kind]) => {
      cy.wrap([...new Set(created[kind])].reverse()).each((name) => {
        cy.frappeDeleteResource(doctype, name, { failOnStatusCode: false });
      });
    });
  });

  cy.then(() => {
    Object.keys(created).forEach((kind) => {
      created[kind].length = 0;
    });
  });
}

describe("MPIT document creation through Desk forms", () => {
  beforeEach(() => {
    cy.frappeLogin();
  });

  afterEach(() => {
    cleanupCreatedDocuments();
  });

  it("creates an MPIT Vendor from the user-facing form", () => {
    const suffix = runId();
    const vendorName = `Vendor UI ${suffix}`;

    cy.openNewDoc("MPIT Vendor");
    cy.saveCurrentForm(async (_win, frm) => {
      await frm.set_value("vendor_name", vendorName);
      await frm.set_value("is_active", 1);
    }).then(({ name, doc }) => {
      track("vendors", name);
      expect(name).to.eq(vendorName);
      expect(doc.vendor_name).to.eq(vendorName);
    });
  });

  it("creates an MPIT Project from the user-facing form", () => {
    const suffix = runId();
    const projectTitle = `Project UI ${suffix}`;

    createCostCenter(`CC Project UI ${suffix}`).then((costCenter) => {
      cy.openNewDoc("MPIT Project");
      cy.saveCurrentForm(async (_win, frm) => {
        await frm.set_value("title", projectTitle);
        await frm.set_value("workflow_state", "Approved");
        await frm.set_value("cost_center", costCenter);
      }).then(({ name, doc }) => {
        track("projects", name);
        expect(doc.title).to.eq(projectTitle);
        expect(doc.workflow_state).to.eq("Approved");
        expect(doc.cost_center).to.eq(costCenter);
      });
    });
  });

  it("creates an MPIT Contract with a term from the user-facing form", () => {
    const suffix = runId();
    const description = `Contract UI ${suffix}`;

    createVendor(`Vendor Contract UI ${suffix}`).then((vendor) => {
      createCostCenter(`CC Contract UI ${suffix}`).then((costCenter) => {
        cy.openNewDoc("MPIT Contract");
        cy.saveCurrentForm(async (_win, frm) => {
          await frm.set_value("description", description);
          await frm.set_value("vendor", vendor);
          await frm.set_value("cost_center", costCenter);
          await frm.set_value("auto_renew", 0);

          // Child rows are added through the Frappe grid model. This preserves
          // the real save/validation path while avoiding brittle grid clicks.
          const term = frm.add_child("terms");
          term.from_date = `${new Date().getFullYear()}-01-01`;
          term.to_date = `${new Date().getFullYear()}-12-31`;
          term.amount = 1200;
          term.amount_includes_vat = 0;
          term.vat_rate = 0;
          term.billing_cycle = "Annual";
          frm.refresh_field("terms");
        }).then(({ name, doc }) => {
          track("contracts", name);
          expect(doc.description).to.eq(description);
          expect(doc.terms).to.have.length(1);
          expect(doc.terms[0].billing_cycle).to.eq("Annual");
        });
      });
    });
  });

  it("creates an ordinary MPIT Expense with an actual row from the user-facing form", () => {
    const suffix = runId();
    const year = String(new Date().getFullYear());
    const expenseTitle = `Expense UI ${suffix}`;

    createVendor(`Vendor Expense UI ${suffix}`).then((vendor) => {
      createCostCenter(`CC Expense UI ${suffix}`).then((costCenter) => {
        cy.openNewDoc("MPIT Expense");
        cy.saveCurrentFormViaDesk(async (win, frm) => {
          await frm.set_value("expense_kind", "Ordinary");
          await frm.set_value("expense_title", expenseTitle);
          await frm.set_value("year", year);
          await frm.set_value("cost_center", costCenter);
          await frm.set_value("uses_plafond", 0);
          await frm.set_value("is_extra", 0);

          frm.clear_table("rows");
          const row = frm.add_child("rows");
          await win.frappe.model.set_value(row.doctype, row.name, "row_description", "UI actual row");
          await win.frappe.model.set_value(row.doctype, row.name, "row_phase", "Actual");
          await win.frappe.model.set_value(row.doctype, row.name, "row_state", "Active");
          await win.frappe.model.set_value(row.doctype, row.name, "vendor", vendor);
          await win.frappe.model.set_value(row.doctype, row.name, "amount", 100);
          await win.frappe.model.set_value(row.doctype, row.name, "amount_includes_vat", 0);
          await win.frappe.model.set_value(row.doctype, row.name, "vat_rate", 22);
          await win.frappe.model.set_value(row.doctype, row.name, "spend_date", `${year}-03-15`);
          frm.refresh_field("rows");
        }).then(({ name, doc }) => {
          track("expenses", name);
          expect(doc.expense_title).to.eq(expenseTitle);
          expect(doc.rows).to.have.length(1);
          expect(doc.total_actual_net).to.eq(100);
        });
      });
    });
  });
});
