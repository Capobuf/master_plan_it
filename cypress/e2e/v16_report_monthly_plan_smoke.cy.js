/**
 * v16_report_year_end_forecast_smoke.cy.js
 *
 * Smoke tests for the MPIT Year End Forecast report on Frappe v16.
 *
 * Protects against:
 *   - Report page not resolving (report not registered in v16 migration)
 *   - mpit_year_end_forecast.js failing to load or execute
 *   - Filter form not rendered (would indicate broken report JS wiring)
 *   - onload crashing on fresh install (no fiscal_year user default)
 *
 * Filters defined in mpit_year_end_forecast.js:
 *   - year        (Link -> MPIT Year, required)
 *   - cost_center (Link -> MPIT Cost Center)
 *
 * Asserting [data-fieldname] inputs exist confirms the report JS was fetched,
 * parsed, and Frappe built the filter form from the JS definition — not just
 * that an empty shell loaded.
 */

describe("v16 - Report: MPIT Year End Forecast", () => {
  beforeEach(() => {
    cy.frappeLogin();
  });

  it("report page loads and displays correct title", () => {
    cy.openReport("MPIT Year End Forecast");
    cy.contains("MPIT Year End Forecast", { timeout: 15000 }).should("be.visible");
  });

  it("report JS is active: required filter inputs are present", () => {
    cy.openReport("MPIT Year End Forecast");
    // [data-fieldname] is Frappe's stable attribute on all filter/form fields.
    // These filters are defined exclusively in mpit_year_end_forecast.js; their
    // presence proves the JS file loaded and Frappe wired the filter form.
    cy.get('[data-fieldname="year"] input', { timeout: 15000 }).should("exist");
    cy.get('[data-fieldname="cost_center"] input', { timeout: 10000 }).should("exist");
    cy.get('[data-fieldname="period"]', { timeout: 10000 }).should("exist");
  });
});
