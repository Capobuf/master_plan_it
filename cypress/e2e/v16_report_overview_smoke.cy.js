/**
 * v16_report_overview_smoke.cy.js
 *
 * Smoke tests for the MPIT Overview report on Frappe v16.
 */

describe("v16 — Report: MPIT Overview", () => {
  beforeEach(() => {
    cy.frappeLogin();
  });

  it("report page loads and displays correct title", () => {
    cy.openReport("MPIT Overview");
    cy.contains("MPIT Overview", { timeout: 15000 }).should("be.visible");
  });

  it("core visible filters are rendered", () => {
    cy.openReport("MPIT Overview");

    [
      "year",
      "financial_view",
      "view_mode",
      "cost_center",
      "show_zero_rows",
      "print_profile",
      "print_orientation",
      "print_density",
    ].forEach((fieldname) => {
      cy.get(`[data-fieldname="${fieldname}"]`, { timeout: 15000 }).should("be.visible");
    });
  });
});
