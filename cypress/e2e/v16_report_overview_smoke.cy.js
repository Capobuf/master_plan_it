/**
 * v16_report_economic_position_smoke.cy.js
 *
 * Smoke tests for the MPIT Economic Position report on Frappe v16.
 */

describe("v16 - Report: MPIT Economic Position", () => {
  beforeEach(() => {
    cy.frappeLogin();
  });

  it("report page loads and displays correct title", () => {
    cy.openReport("MPIT Economic Position");
    cy.contains("MPIT Economic Position", { timeout: 15000 }).should("be.visible");
  });

  it("core visible filters are rendered", () => {
    cy.openReport("MPIT Economic Position");

    [
      "year",
      "cost_center",
      "group_by",
      "basis",
      "scope",
      "hide_zero_rows",
      "print_profile",
      "print_orientation",
      "print_density",
    ].forEach((fieldname) => {
      cy.get(`[data-fieldname="${fieldname}"]`, { timeout: 15000 }).should("be.visible");
    });
  });
});
