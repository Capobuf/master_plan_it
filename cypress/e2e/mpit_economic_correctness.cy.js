/**
 * Economic correctness coverage for Master Plan IT.
 *
 * The UI creation spec covers the primary user-facing save path. This spec is
 * deliberately API-driven for fixture setup so each test can build a compact,
 * deterministic matrix of economic combinations, then assert the official
 * Frappe report outputs. That keeps the tests independent, fast, and focused
 * on business correctness rather than Desk widget internals.
 *
 * All documents get unique CY-* names and are deleted after each test. Cleanup
 * is intentionally broader than the explicit created list for contracts because
 * contract save hooks generate MPIT Expense documents as economic source rows.
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

function yearName() {
  return String(new Date().getFullYear());
}

function track(kind, name) {
  if (name) {
    created[kind].push(name);
  }
  return name;
}

function money(value) {
  return Number(Number(value || 0).toFixed(2));
}

function createVendor(label) {
  return cy
    .frappePost("/api/resource/MPIT Vendor", {
      doctype: "MPIT Vendor",
      vendor_name: label,
      is_active: 1,
    })
    .then((res) => track("vendors", res.body?.data?.name));
}

function createCostCenter(label) {
  return cy
    .frappePost("/api/resource/MPIT Cost Center", {
      doctype: "MPIT Cost Center",
      cost_center_name: label,
      parent_mpit_cost_center: "All Cost Centers",
      is_group: 0,
    })
    .then((res) => track("costCenters", res.body?.data?.name));
}

function createProject(title, costCenter, workflowState) {
  return cy
    .frappePost("/api/resource/MPIT Project", {
      doctype: "MPIT Project",
      title,
      workflow_state: workflowState,
      cost_center: costCenter,
    })
    .then((res) => track("projects", res.body?.data?.name));
}

function createExpense(doc) {
  return cy.frappePost("/api/resource/MPIT Expense", doc).then((res) => {
    expect(res.body?.data?.name, "created expense").to.be.a("string").and.not.be.empty;
    return track("expenses", res.body.data.name);
  });
}

function createContract(doc) {
  return cy.frappePost("/api/resource/MPIT Contract", doc).then((res) => {
    expect(res.body?.data?.name, "created contract").to.be.a("string").and.not.be.empty;
    return track("contracts", res.body.data.name);
  });
}

function ordinaryExpense({ title, year, costCenter, vendor, project, contract, usesPlafond = 0, isExtra = 0, plafondExpense, rows }) {
  return {
    doctype: "MPIT Expense",
    expense_kind: "Ordinary",
    expense_title: title,
    year,
    cost_center: costCenter,
    project,
    contract,
    uses_plafond: usesPlafond,
    is_extra: isExtra,
    plafond_expense: plafondExpense,
    rows: rows.map((row) => ({
      doctype: "MPIT Expense Row",
      row_description: row.description,
      row_phase: row.phase,
      row_state: row.state || "Active",
      vendor,
      amount: row.amount,
      amount_includes_vat: row.includesVat ? 1 : 0,
      vat_rate: row.vatRate ?? 0,
      spend_date: row.spendDate,
      start_date: row.startDate,
      end_date: row.endDate,
      distribution: row.distribution,
      qty: row.qty,
      unit_price: row.unitPrice,
    })),
  };
}

function plafondExpense({ title, year, costCenter, rows }) {
  return {
    doctype: "MPIT Expense",
    expense_kind: "Plafond",
    expense_title: title,
    year,
    cost_center: costCenter,
    rows: rows.map((row) => ({
      doctype: "MPIT Expense Row",
      row_description: row.description,
      row_state: row.state || "Active",
      amount: row.amount,
      amount_includes_vat: row.includesVat ? 1 : 0,
      vat_rate: row.vatRate ?? 0,
      spend_date: row.spendDate,
    })),
  };
}

function reportRows(payload) {
  return payload.result || payload.data || [];
}

function reportSummaryMap(payload) {
  const out = {};
  (payload.report_summary || []).forEach((item) => {
    out[item.label] = money(item.value);
  });
  return out;
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
  cy.wrap([...created.contracts]).each((contractName) => {
    findGeneratedExpensesForContract(contractName);
  });

  cy.then(() => {
    [
      ["MPIT Expense", "expenses"],
      ["MPIT Contract", "contracts"],
      ["MPIT Project", "projects"],
      ["MPIT Vendor", "vendors"],
      ["MPIT Cost Center", "costCenters"],
    ].forEach(([doctype, kind]) => {
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

describe("MPIT economic correctness", () => {
  beforeEach(() => {
    cy.frappeLogin();
  });

  afterEach(() => {
    cleanupCreatedDocuments();
  });

  it("calculates forecast, actual, project-state buckets, and plafond consumption", () => {
    const suffix = runId();
    const year = yearName();

    createVendor(`Vendor Econ ${suffix}`).then((vendor) => {
      createCostCenter(`CC Econ ${suffix}`).then((costCenter) => {
        createProject(`Approved Project ${suffix}`, costCenter, "Approved").then((approvedProject) => {
          createProject(`Proposed Project ${suffix}`, costCenter, "Proposed").then((proposedProject) => {
            createProject(`Idea Project ${suffix}`, costCenter, "Idea").then((ideaProject) => {
              // A plafond document is its own economic bucket. It funds only
              // ordinary actual rows that explicitly reference it.
              createExpense(
                plafondExpense({
                  title: `Plafond Econ ${suffix}`,
                  year,
                  costCenter,
                  rows: [{ description: "Plafond capacity", amount: 1000, vatRate: 0, spendDate: `${year}-01-01` }],
                })
              ).then((plafondName) => {
                createExpense(
                  ordinaryExpense({
                    title: `Approved ordinary ${suffix}`,
                    year,
                    costCenter,
                    vendor,
                    project: approvedProject,
                    rows: [
                      { description: "Approved estimate", phase: "Estimate", amount: 100, vatRate: 0, spendDate: `${year}-02-01` },
                      { description: "Approved quote", phase: "Quote", amount: 50, vatRate: 0, spendDate: `${year}-03-01` },
                      { description: "Approved actual", phase: "Actual", amount: 30, vatRate: 0, spendDate: `${year}-04-01` },
                    ],
                  })
                );

                createExpense(
                  ordinaryExpense({
                    title: `Proposed ordinary ${suffix}`,
                    year,
                    costCenter,
                    vendor,
                    project: proposedProject,
                    rows: [
                      { description: "Proposed estimate", phase: "Estimate", amount: 200, vatRate: 0, spendDate: `${year}-02-10` },
                      // Proposed actual rows are created to prove they are
                      // excluded from actual totals by project-state rules.
                      { description: "Proposed actual excluded", phase: "Actual", amount: 40, vatRate: 0, spendDate: `${year}-04-10` },
                    ],
                  })
                );

                createExpense(
                  ordinaryExpense({
                    title: `Idea ordinary ${suffix}`,
                    year,
                    costCenter,
                    vendor,
                    project: ideaProject,
                    rows: [
                      // Idea forecast is exposed in the Ideas bucket but does
                      // not contribute to operational forecast totals.
                      { description: "Idea estimate bucket", phase: "Estimate", amount: 300, vatRate: 0, spendDate: `${year}-02-20` },
                    ],
                  })
                );

                createExpense(
                  ordinaryExpense({
                    title: `Extra ordinary ${suffix}`,
                    year,
                    costCenter,
                    vendor,
                    isExtra: 1,
                    rows: [
                      { description: "Extra actual", phase: "Actual", amount: 70, vatRate: 0, spendDate: `${year}-05-01` },
                      { description: "Cancelled actual ignored", phase: "Actual", state: "Cancelled", amount: 999, vatRate: 0, spendDate: `${year}-05-02` },
                    ],
                  })
                );

                createExpense(
                  ordinaryExpense({
                    title: `On plafond ordinary ${suffix}`,
                    year,
                    costCenter,
                    vendor,
                    usesPlafond: 1,
                    plafondExpense: plafondName,
                    rows: [
                      { description: "On plafond actual", phase: "Actual", amount: 600, vatRate: 0, spendDate: `${year}-06-01` },
                    ],
                  })
                );

                cy.runReport("MPIT Overview", {
                  year,
                  cost_center: costCenter,
                  financial_view: "Actual with Estimates and Quotes",
                  view_mode: "Summary",
                  show_zero_rows: 1,
                }).then((payload) => {
                  const rows = reportRows(payload);
                  expect(rows, "overview rows").to.have.length(1);
                  const row = rows[0];

                  expect(money(row.forecast_estimate)).to.eq(300);
                  expect(money(row.forecast_quote)).to.eq(50);
                  expect(money(row.forecast_total)).to.eq(350);
                  expect(money(row.approved_budget)).to.eq(150);
                  expect(money(row.proposals)).to.eq(200);
                  expect(money(row.ideas)).to.eq(300);
                  expect(money(row.actual_standard)).to.eq(30);
                  expect(money(row.actual_on_plafond)).to.eq(600);
                  expect(money(row.actual_extra)).to.eq(70);
                  expect(money(row.actual_total)).to.eq(700);
                  expect(money(row.plafond)).to.eq(1000);
                  expect(money(row.plafond_consumed)).to.eq(600);
                  expect(money(row.remaining)).to.eq(400);
                  expect(money(row.over)).to.eq(0);

                  const summary = reportSummaryMap(payload);
                  expect(summary["Forecast Budget"]).to.eq(350);
                  expect(summary["Actual Spend"]).to.eq(700);
                  expect(summary.Plafond).to.eq(1000);
                });
              });
            });
          });
        });
      });
    });
  });

  it("splits VAT, unit-price rows, and monthly distributions into the Monthly Plan", () => {
    const suffix = runId();
    const year = yearName();

    createVendor(`Vendor Monthly ${suffix}`).then((vendor) => {
      createCostCenter(`CC Monthly ${suffix}`).then((costCenter) => {
        createExpense(
          ordinaryExpense({
            title: `Monthly allocation ${suffix}`,
            year,
            costCenter,
            vendor,
            rows: [
              // 122 gross at 22% VAT becomes 100 net and lands in January.
              { description: "Gross forecast", phase: "Estimate", amount: 122, includesVat: true, vatRate: 22, spendDate: `${year}-01-15` },
              // qty * unit_price recomputes amount to 200 net and lands in February.
              { description: "Unit forecast", phase: "Quote", amount: 0, qty: 2, unitPrice: 100, vatRate: 0, spendDate: `${year}-02-15` },
              // Distribution "all" spreads the row evenly across March-April.
              { description: "Actual spread", phase: "Actual", amount: 300, vatRate: 0, startDate: `${year}-03-01`, endDate: `${year}-04-30`, distribution: "all" },
              // Distribution "end" puts the full amount in the last touched month.
              { description: "Actual end", phase: "Actual", amount: 80, vatRate: 0, startDate: `${year}-05-01`, endDate: `${year}-06-30`, distribution: "end" },
            ],
          })
        );

        cy.runReport("MPIT Monthly Plan", { year, cost_center: costCenter }).then((payload) => {
          const byMonth = {};
          reportRows(payload).forEach((row) => {
            byMonth[row.month] = row;
          });

          expect(money(byMonth.Jan.forecast)).to.eq(100);
          expect(money(byMonth.Feb.forecast)).to.eq(200);
          expect(money(byMonth.Mar.actual)).to.eq(150);
          expect(money(byMonth.Apr.actual)).to.eq(150);
          expect(money(byMonth.Jun.actual)).to.eq(80);

          const summary = reportSummaryMap(payload);
          expect(summary.Forecast).to.eq(300);
          expect(summary.Actual).to.eq(380);
          expect(summary.Delta).to.eq(-80);
        });
      });
    });
  });

  it("syncs contract terms into official expense economics without double counting contracts", () => {
    const suffix = runId();
    const year = yearName();

    createVendor(`Vendor Contract Econ ${suffix}`).then((vendor) => {
      createCostCenter(`CC Contract Econ ${suffix}`).then((costCenter) => {
        createProject(`Contract Project ${suffix}`, costCenter, "Approved").then((project) => {
          createContract({
            doctype: "MPIT Contract",
            description: `Contract Econ ${suffix}`,
            vendor,
            cost_center: costCenter,
            project,
            terms: [
              {
                doctype: "MPIT Contract Term",
                from_date: `${year}-01-01`,
                to_date: `${year}-12-31`,
                amount: 1200,
                amount_includes_vat: 0,
                vat_rate: 0,
                billing_cycle: "Annual",
              },
            ],
          }).then((contractName) => {
            findGeneratedExpensesForContract(contractName);

            cy.then(() => {
              expect(created.expenses, "generated contract expense").to.have.length.gte(1);
            });

            cy.runReport("MPIT Overview", {
              year,
              cost_center: costCenter,
              financial_view: "Actual with Estimates and Quotes",
              view_mode: "Summary",
              show_zero_rows: 1,
            }).then((payload) => {
              const rows = reportRows(payload);
              expect(rows).to.have.length(1);
              expect(money(rows[0].forecast_contracts)).to.eq(0);
              expect(money(rows[0].forecast_total)).to.eq(0);
              expect(money(rows[0].actual_standard)).to.eq(1200);
              expect(money(rows[0].actual_total)).to.eq(1200);
            });

            cy.runReport("MPIT Monthly Plan", { year, cost_center: costCenter, contract: contractName }).then((payload) => {
              const rows = reportRows(payload);
              rows.forEach((row) => {
                expect(money(row.actual), `${row.month} actual`).to.eq(100);
              });
              expect(reportSummaryMap(payload).Actual).to.eq(1200);
            });

            cy.runReport("MPIT Project Forecast vs Actual", { year, cost_center: costCenter }).then((payload) => {
              const projectRows = reportRows(payload).filter((row) => row.project === project);
              expect(projectRows, "project report row").to.have.length(1);
              expect(money(projectRows[0].actual)).to.eq(1200);
              expect(money(projectRows[0].forecast)).to.eq(0);
              expect(money(projectRows[0].variance)).to.eq(-1200);
            });
          });
        });
      });
    });
  });
});
