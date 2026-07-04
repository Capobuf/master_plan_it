/**
 * v16_workspace_smoke.cy.js
 *
 * Smoke tests for the Master Plan IT workspace on Frappe v16.
 *
 * Workspace source: master_plan_it/workspace/master_plan_it/master_plan_it.json
 *   name: "Master Plan IT"
 *   URL slug: /app/master-plan-it
 */

const ADMIN_PASSWORD = Cypress.env("ADMIN_PASSWORD") || Cypress.env("FRAPPE_PASSWORD") || "admin";

describe("v16 — Workspace Smoke", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("workspace page is reachable and title is correct", () => {
    cy.visit("/app/master-plan-it");
    cy.contains("Master Plan IT", { timeout: 15000 }).should("be.visible");
  });

  it("workspace shortcuts are rendered", () => {
    cy.visit("/app/master-plan-it");

    const shortcuts = [
      "New Expense",
      "New Plafond",
      "New Contract",
      "New Project",
      "Panoramica Economica",
      "Monthly Plan",
    ];

    shortcuts.forEach((label) => {
      cy.contains(label, { timeout: 10000 }).should("be.visible");
    });
  });

  it("workspace charts are rendered", () => {
    cy.visit("/app/master-plan-it");

    const charts = [
      "Forecast vs Actual by Cost Center",
      "Monthly Forecast vs Actual",
      "Plafond Usage by Cost Center",
    ];

    charts.forEach((label) => {
      cy.contains(label, { timeout: 20000 }).should("be.visible");
    });
    cy.get(".widget-charts .dashboard-widget-box", { timeout: 20000 }).should("have.length", 3);
  });

  it("'Monthly Plan' shortcut navigates to MPIT Monthly Plan report", () => {
    cy.visit("/app/master-plan-it");

    cy.on("uncaught:exception", (err) => {
      console.error("App exception on report page:", err.message);
      return false;
    });

    cy.contains("Monthly Plan", { timeout: 10000 }).click();
    cy.url({ timeout: 15000 }).should("include", "Monthly%20Plan");
    cy.get('[data-fieldname="year"] input', { timeout: 15000 }).should("be.visible");
  });
});
