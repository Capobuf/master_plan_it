const createdExpenses = [];
const createdVendors = [];

function fetchYearAndCostCenter() {
  return cy
    .request({
      method: "GET",
      url: '/api/resource/MPIT Year?limit=1&fields=["name"]',
    })
    .then((yearRes) => {
      const year = yearRes.body?.data?.[0]?.name;
      expect(year, "MPIT Year available").to.be.a("string").and.not.be.empty;

      return cy
        .request({
          method: "GET",
          url: '/api/resource/MPIT Cost Center?limit=1&fields=["name"]',
        })
        .then((ccRes) => {
          const costCenter = ccRes.body?.data?.[0]?.name;
          expect(costCenter, "MPIT Cost Center available").to.be.a("string").and.not.be.empty;
          return { year, costCenter };
        });
    });
}

describe("MPIT quick entry and row replacement selector", () => {
  beforeEach(() => {
    cy.frappeLogin();
  });

  afterEach(() => {
    cy.wrap([...createdExpenses].reverse()).each((expenseName) => {
      cy.frappeDeleteResource("MPIT Expense", expenseName, { failOnStatusCode: false });
    });
    cy.wrap([...createdVendors].reverse()).each((vendorName) => {
      cy.frappeDeleteResource("MPIT Vendor", vendorName, { failOnStatusCode: false });
    });
    cy.then(() => {
      createdExpenses.length = 0;
      createdVendors.length = 0;
    });
  });

  it("MPIT Vendor quick entry shows all required fields", () => {
    cy.visit("/desk");
    cy.window().then((win) => win.frappe.ui.form.make_quick_entry("MPIT Vendor"));

    cy.get('.modal.show [data-fieldname="vendor_name"] input', { timeout: 15000 }).should("exist");
    cy.get(".modal.show .btn-modal-close").first().click({ force: true });
  });

  it("Expense row replacement selector query returns only sibling rows", () => {
    const suffix = Date.now();

    fetchYearAndCostCenter().then(({ year, costCenter }) => {
      cy.frappePost("/api/resource/MPIT Vendor", {
        doctype: "MPIT Vendor",
        vendor_name: `Cypress Vendor ${suffix}`,
        is_active: 1,
      }).then((vendorRes) => {
        const vendor = vendorRes.body?.data?.name;
        expect(vendor, "created vendor").to.be.a("string").and.not.be.empty;
        createdVendors.push(vendor);

        createExpensePair(suffix, year, costCenter, vendor);
      });
    });
  });
});

function createExpensePair(suffix, year, costCenter, vendor) {
  cy.frappePost("/api/resource/MPIT Expense", {
    doctype: "MPIT Expense",
    expense_kind: "Ordinary",
    expense_title: `Cypress RowRef A ${suffix}`,
    year,
    cost_center: costCenter,
    uses_plafond: 0,
    is_extra: 1,
    rows: [
      {
        doctype: "MPIT Expense Row",
        row_description: "A-1",
        row_phase: "Estimate",
        row_state: "Active",
        vendor,
        amount: 100,
        amount_includes_vat: 0,
        vat_rate: 22,
        spend_date: `${year}-01-10`,
      },
      {
        doctype: "MPIT Expense Row",
        row_description: "A-2",
        row_phase: "Quote",
        row_state: "Active",
        vendor,
        amount: 120,
        amount_includes_vat: 0,
        vat_rate: 22,
        spend_date: `${year}-02-10`,
      },
      {
        doctype: "MPIT Expense Row",
        row_description: "A-3",
        row_phase: "Actual",
        row_state: "Active",
        vendor,
        amount: 140,
        amount_includes_vat: 0,
        vat_rate: 22,
        spend_date: `${year}-03-10`,
      },
    ],
  }).then((aRes) => {
    const expenseA = aRes.body?.data;
    expect(expenseA?.name).to.be.a("string");
    expect(expenseA?.rows?.length).to.eq(3);
    createdExpenses.push(expenseA.name);

    cy.frappePost("/api/resource/MPIT Expense", {
      doctype: "MPIT Expense",
      expense_kind: "Ordinary",
      expense_title: `Cypress RowRef B ${suffix}`,
      year,
      cost_center: costCenter,
      uses_plafond: 0,
      is_extra: 1,
      rows: [
        {
          doctype: "MPIT Expense Row",
          row_description: "B-1",
          row_phase: "Estimate",
          row_state: "Active",
          vendor,
          amount: 80,
          amount_includes_vat: 0,
          vat_rate: 22,
          spend_date: `${year}-04-10`,
        },
      ],
    }).then((bRes) => {
      const expenseB = bRes.body?.data;
      expect(expenseB?.name).to.be.a("string");
      expect(expenseB?.rows?.length).to.eq(1);
      createdExpenses.push(expenseB.name);

      cy.visit(`/desk/mpit-expense/${encodeURIComponent(expenseA.name)}`);
      cy.get(".form-layout", { timeout: 15000 }).should("be.visible");

      cy.window()
        .then((win) => {
          const frm = win.cur_frm;
          const currentRow = frm.doc.rows[2];
          const rowField = frm.fields_dict.rows.grid.get_field("replaces_row_name");
          const queryConfig = rowField.get_query(frm.doc, currentRow.doctype, currentRow.name);

          expect(queryConfig?.query).to.eq(
            "master_plan_it.master_plan_it.doctype.mpit_expense.mpit_expense.get_expense_row_replacement_options"
          );
          expect(queryConfig?.filters?.parent_expense).to.eq(expenseA.name);
          expect(queryConfig?.filters?.current_row_name).to.eq(currentRow.name);

          return queryConfig;
        })
        .then((queryConfig) => {
          cy.request({
            method: "GET",
            url: "/api/method/frappe.desk.search.search_link",
            qs: {
              doctype: "MPIT Expense Row",
              txt: "",
              query: queryConfig.query,
              filters: JSON.stringify(queryConfig.filters),
              searchfield: "name",
              page_length: 20,
            },
          }).then((searchRes) => {
            const names = (searchRes.body?.message || []).map((item) => item.value);
            expect(names).to.include(expenseA.rows[0].name);
            expect(names).to.include(expenseA.rows[1].name);
            expect(names).to.not.include(expenseA.rows[2].name);
            expect(names).to.not.include(expenseB.rows[0].name);
          });
        });
    });
  });
}
