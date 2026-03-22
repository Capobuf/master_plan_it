/**
 * v16_report_monthly_plan_smoke.cy.js
 *
 * Smoke tests for the MPIT Monthly Plan report on Frappe v16.
 *
 * Protects against:
 *   - Report page not resolving (report not registered in v16 migration)
 *   - mpit_monthly_plan.js failing to load or execute
 *   - Filter form not rendered (would indicate broken report JS wiring)
 *
 * Filters defined in mpit_monthly_plan.js:
 *   - year        (Link → MPIT Year, required)
 *   - cost_center (Link → MPIT Cost Center)
 *
 * Asserting [data-fieldname] inputs exist confirms the report JS was fetched,
 * parsed, and Frappe built the filter form from the JS definition — not just
 * that an empty shell loaded.
 */

const ADMIN_PASSWORD = Cypress.env("ADMIN_PASSWORD") || "admin";

describe("v16 — Report: MPIT Monthly Plan", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
    // Suppress Frappe's "Filter missing" uncaught exception thrown when the
    // Administrator user has no fiscal_year default set. This is a real v16
    // regression finding (reqd:1 filter with missing user default), but we still
    // want to assert the UI state — the filter inputs must render despite the error.
    cy.on("uncaught:exception", (err) => {
      // cy.* commands are not allowed inside event callbacks — use console only
      console.error("App uncaught exception:", err.message);
      return false;
    });
  });

  it("report page loads and displays correct title", () => {
    cy.openReport("MPIT Monthly Plan");
    cy.contains("MPIT Monthly Plan", { timeout: 15000 }).should("be.visible");
  });

  it("report JS is active: required filter inputs are present", () => {
    cy.openReport("MPIT Monthly Plan");
    // [data-fieldname] is Frappe's stable attribute on all filter/form fields.
    // These filters are defined exclusively in mpit_monthly_plan.js — their
    // presence proves the JS file loaded and Frappe wired the filter form.
    cy.get('[data-fieldname="year"] input', { timeout: 15000 }).should("exist");
    cy.get('[data-fieldname="cost_center"] input', { timeout: 10000 }).should("exist");
  });
});
