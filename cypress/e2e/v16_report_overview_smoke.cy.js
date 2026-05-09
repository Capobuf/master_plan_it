/**
 * v16_report_overview_smoke.cy.js
 *
 * Smoke tests for the MPIT Overview report on Frappe v16.
 */

const ADMIN_PASSWORD = Cypress.env("ADMIN_PASSWORD") || "admin";

describe("v16 — Report: MPIT Overview", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("report page loads and displays correct title", () => {
    cy.openReport("MPIT Overview");
    cy.contains("MPIT Overview", { timeout: 15000 }).should("be.visible");
  });

  it("core visible filters are rendered", () => {
    cy.openReport("MPIT Overview");

    cy.get('[data-fieldname="year"] input', { timeout: 15000 }).should("exist");
    cy.get('[data-fieldname="view_mode"] input', { timeout: 10000 }).should("exist");
    cy.get('[data-fieldname="cost_center"] input', { timeout: 10000 }).should("exist");
    cy.get('[data-fieldname="show_zero_rows"] input', { timeout: 10000 }).should("exist");
    cy.get('[data-fieldname="print_profile"] input', { timeout: 10000 }).should("exist");
    cy.get('[data-fieldname="print_orientation"] input', { timeout: 10000 }).should("exist");
    cy.get('[data-fieldname="print_density"] input', { timeout: 10000 }).should("exist");
  });
});
