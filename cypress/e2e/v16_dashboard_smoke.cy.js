/**
 * v16_dashboard_smoke.cy.js
 *
 * Smoke tests for the Master Plan IT Overview dashboard on Frappe v16.
 *
 * Protects against:
 *   - Dashboard page failing to render after v16 migration
 *   - Number card or chart widget containers silently failing to mount
 *   - Full-page JS crash / error banners at dashboard level
 *
 * Dashboard source:
 *   master_plan_it/dashboard/master_plan_it_overview/master_plan_it_overview.json
 *   name: "Master Plan IT Overview"
 *   Contains 13 number cards and 13 chart widgets.
 *
 * Note: these tests assert that widget containers MOUNT (DOM elements created),
 * not that they contain data. Data-level emptiness is expected on a fresh install.
 */

const ADMIN_PASSWORD = Cypress.env("ADMIN_PASSWORD") || "admin";

describe("v16 — Dashboard Smoke", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("dashboard page loads without crash", () => {
    // Frappe v16 dashboard route: /app/dashboard-view/{URL-encoded name}
    cy.visit("/app/dashboard-view/Master%20Plan%20IT%20Overview");
    cy.contains("Master Plan IT Overview", { timeout: 20000 }).should("be.visible");
  });

  it("number card widgets mount in the DOM", () => {
    cy.visit("/app/dashboard-view/Master%20Plan%20IT%20Overview");
    cy.contains("Master Plan IT Overview", { timeout: 20000 }).should("exist");
    // Frappe v16 number card widget class confirmed from frappe/widgets/number_card_widget.js:
    // this.widget.addClass("number-widget-box")
    // Dashboard defines 13 number cards — asserting > 0 confirms component initialized.
    cy.get(".number-widget-box", { timeout: 20000 }).should("have.length.gt", 0);
  });

  it("chart widgets mount in the DOM", () => {
    cy.visit("/app/dashboard-view/Master%20Plan%20IT%20Overview");
    cy.contains("Master Plan IT Overview", { timeout: 20000 }).should("exist");
    // Frappe v16 chart widget class confirmed from frappe/widgets/chart_widget.js:
    // this.widget.addClass("dashboard-widget-box")
    // Dashboard defines 13 charts — asserting > 0 confirms component initialized.
    cy.get(".dashboard-widget-box", { timeout: 20000 }).should("have.length.gt", 0);
  });
});
