/**
 * v16_report_overview_smoke.cy.js
 *
 * Smoke tests for the MPIT Overview (Panoramica) report on Frappe v16.
 *
 * Why MPIT Overview as the secondary report:
 *   It is the primary executive analytics view for the app ("Panoramica").
 *   It is exposed via the "Panoramica" workspace shortcut, as a dashboard chart
 *   source (MPIT Plan vs Cap vs Actual), and drives the most important
 *   budget-tracking surface. A failure here has broad user-facing impact.
 *   It also renders a custom extra-charts container (mpit-extra-charts) that
 *   adds JS complexity not present in simpler reports.
 *
 * Protects against:
 *   - Report page not resolving after v16 migration
 *   - mpit_overview.js failing to load or execute
 *   - Filter form not rendered (broken report JS wiring)
 *
 * Filters defined in mpit_overview.js:
 *   - year        (Link → MPIT Year)
 *   - budget      (Link → MPIT Budget, with get_query)
 *   - cost_center (Link → MPIT Cost Center)
 *   - vendor      (Link → MPIT Vendor)
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

  it("report JS is active: all four filter inputs are rendered", () => {
    cy.openReport("MPIT Overview");
    // All four filters are defined in mpit_overview.js. Their presence confirms
    // the report JS executed and Frappe built the complete filter form.
    cy.get('[data-fieldname="year"] input', { timeout: 15000 }).should("exist");
    cy.get('[data-fieldname="budget"] input', { timeout: 10000 }).should("exist");
    cy.get('[data-fieldname="cost_center"] input', { timeout: 10000 }).should("exist");
    cy.get('[data-fieldname="vendor"] input', { timeout: 10000 }).should("exist");
  });
});
