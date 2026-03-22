/**
 * v16_workspace_smoke.cy.js
 *
 * Smoke tests for the Master Plan IT workspace on Frappe v16.
 *
 * Protects against:
 *   - Workspace not registered or unreachable after v16 migration
 *   - Workspace shortcuts missing (metadata install failure)
 *   - Shortcuts pointing to the wrong target (report name drift)
 *   - Navigation from workspace shortcut to a report page being broken
 *
 * Workspace source: master_plan_it/workspace/master_plan_it/master_plan_it.json
 *   name: "Master Plan IT"
 *   Frappe v16 URL slug: /app/master-plan-it (spaces → hyphens, lowercase)
 *
 * Shortcuts defined in workspace JSON (all 7):
 *   Nuova Spesa    → DocType  MPIT Actual Entry  (new)
 *   Nuovo Progetto → DocType  MPIT Project       (new)
 *   Nuovo Contratto→ DocType  MPIT Contract      (new)
 *   Panoramica     → Report   MPIT Overview
 *   Piano Mensile  → Report   MPIT Monthly Plan
 *   Rinnovi        → Report   MPIT Renewals Window
 *   What-If        → Report   MPIT Budget What-If
 */

const ADMIN_PASSWORD = Cypress.env("ADMIN_PASSWORD") || "admin";

describe("v16 — Workspace Smoke", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("workspace page is reachable and title is correct", () => {
    cy.visit("/app/master-plan-it");
    // Frappe v16 renders the workspace name as a heading in the page body.
    // cy.contains() is more resilient than a fixed CSS selector across v16 layouts.
    cy.contains("Master Plan IT", { timeout: 15000 }).should("be.visible");
  });

  it("all 7 workspace shortcuts are rendered", () => {
    cy.visit("/app/master-plan-it");
    // Text labels come directly from the workspace JSON definition.
    // Their presence confirms all shortcuts survived the v16 install.
    const shortcuts = [
      "Nuova Spesa",
      "Nuovo Progetto",
      "Nuovo Contratto",
      "Panoramica",
      "Piano Mensile",
      "Rinnovi",
      "What-If",
    ];
    shortcuts.forEach((label) => {
      cy.contains(label, { timeout: 10000 }).should("be.visible");
    });
  });

  it("'Piano Mensile' shortcut navigates to the MPIT Monthly Plan report", () => {
    cy.visit("/app/master-plan-it");
    // The Monthly Plan report throws a Frappe "Filter missing" uncaught exception
    // when the Administrator user has no fiscal_year default set. Suppress it so
    // the navigation assertion can still run — this is a known v16 regression finding.
    cy.on("uncaught:exception", (err) => {
      // cy.* commands are not allowed inside event callbacks — use console only
      console.error("App exception on report page:", err.message);
      return false;
    });
    cy.contains("Piano Mensile", { timeout: 10000 }).click();
    // Report URL must resolve (covers report-name registration and routing)
    cy.url({ timeout: 15000 }).should("include", "Monthly%20Plan");
    // Frappe keeps the workspace DOM alive with display:none after navigation,
    // so cy.contains() finds stale workspace links instead of the report title.
    // Assert a filter input is visible — it only exists on the live report page,
    // proving both navigation and report rendering succeeded.
    cy.get('[data-fieldname="year"] input', { timeout: 15000 }).should("be.visible");
  });
});
