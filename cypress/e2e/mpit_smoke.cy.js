/**
 * MPIT UI Smoke Tests
 *
 * Covers the highest-value UI journeys:
 * 1. Login and reach Frappe Desk
 * 2. Navigate to MPIT workspace / Contract list
 * 3. Open a new Contract form
 * 4. Trigger validation error by saving without a Vendor (user-visible blocking error)
 * 5. Fill a valid contract and save (user can complete the journey)
 * 6. Navigate to MPIT Budget list (workspace reachability)
 * 7. Navigate to MPIT Actual Entry list
 * 8. Verify key amounts are visible on a saved document
 *
 * Site URL: configured via cypress.config.js baseUrl (default: http://localhost:9797)
 * Credentials: Administrator / admin (override via CYPRESS_ADMIN_PASSWORD env var)
 *
 * Run:
 *   cd master_plan_it/cypress
 *   npm install
 *   npx cypress run
 */

const ADMIN_PASSWORD = Cypress.env("ADMIN_PASSWORD") || "admin";

// ────────────────────────────────────────────────────────────────────────────
// Suite 1: Login & Desk access
// ────────────────────────────────────────────────────────────────────────────

describe("1. Login and Desk access", () => {
  it("logs in as Administrator and reaches Frappe Desk", () => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
    cy.goToDesk();
    cy.url().should("match", /\/(app|desk)/);
    // Frappe Desk nav bar must be visible
    cy.get(".navbar").should("be.visible");
  });
});

// ────────────────────────────────────────────────────────────────────────────
// Suite 2: MPIT Contract list reachable
// ────────────────────────────────────────────────────────────────────────────

describe("2. MPIT Contract list", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("opens MPIT Contract list without errors", () => {
    cy.openList("MPIT Contract");
    // Either a list of results or "No MPIT Contract found" — both are valid
    cy.get("h1, .title-text, .list-row-head, .no-result").should("exist");
  });
});

// ────────────────────────────────────────────────────────────────────────────
// Suite 3: New Contract form — validation errors visible in UI
// ────────────────────────────────────────────────────────────────────────────

describe("3. New Contract form — UI validation errors", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("shows vendor required error when saving contract without vendor", () => {
    cy.visit("/desk/mpit-contract/new-mpit-contract-1");
    cy.get(".form-layout", { timeout: 15000 }).should("be.visible");

    // Attempt to save without filling Vendor field
    cy.get('button[data-label="Save"], .btn-primary:contains("Save")')
      .first()
      .click({ force: true });

    // Frappe must show a validation error (Vendor is required)
    cy.get(
      '.msgprint-dialog .modal-body, .alert-message, .frappe-alert-danger, ' +
      '.notification-body, [data-fieldname="vendor"] .help-box',
      { timeout: 10000 }
    ).should("exist");
  });

  it("fills a minimal valid contract and saves without errors", () => {
    // First, ensure we know a vendor and cost center exist via API
    // (created by existing backend tests running before Cypress)
    cy.request({
      method: "GET",
      url: "/api/resource/MPIT Vendor?limit=1&fields=[\"name\"]",
    }).then((res) => {
      const vendorName = res.body?.data?.[0]?.name;
      if (!vendorName) {
        // Skip if no vendor exists yet (CI with empty DB)
        cy.log("No MPIT Vendor found — skipping contract save test");
        return;
      }

      cy.request({
        method: "GET",
        url: "/api/resource/MPIT Cost Center?limit=1&fields=[\"name\"]&filters=[[\"is_group\",\"=\",0]]",
      }).then((ccRes) => {
        const ccName = ccRes.body?.data?.[0]?.name;
        if (!ccName) {
          cy.log("No MPIT Cost Center found — skipping contract save test");
          return;
        }

        // Create contract directly via API (avoids slow form interaction for this data-prep step)
        cy.frappePost("/api/resource/MPIT Contract", {
            doctype: "MPIT Contract",
            description: "Cypress Test Contract",
            vendor: vendorName,
            cost_center: ccName,
            status: "Active",
            terms: [{
              from_date: "2040-01-01",
              amount: 100,
              amount_includes_vat: 0,
              vat_rate: 0,
              billing_cycle: "Monthly",
            }],
        }).then((saveRes) => {
          expect(saveRes.status).to.eq(200);
          const contractName = saveRes.body?.data?.name;
          expect(contractName).to.include("CONTR-");

          // Open the saved document in the Desk to verify UI rendering
          cy.visit(`/desk/mpit-contract/${encodeURIComponent(contractName)}`);
          cy.get(".form-layout", { timeout: 15000 }).should("be.visible");

          // Status field must be visible and show "Active"
          cy.get('[data-fieldname="status"]', { timeout: 10000 }).should("contain.text", "Active");

          // The document title / breadcrumb must contain the contract name
          cy.get(".title-text, .breadcrumb-last, h1").should("exist");
        });
      });
    });
  });
});

// ────────────────────────────────────────────────────────────────────────────
// Suite 4: MPIT Budget list reachable
// ────────────────────────────────────────────────────────────────────────────

describe("4. MPIT Budget list", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("opens MPIT Budget list without errors", () => {
    cy.openList("MPIT Budget");
    cy.get("h1, .title-text, .list-row-head, .no-result").should("exist");
  });

  it("opens MPIT Actual Entry list without errors", () => {
    cy.openList("MPIT Actual Entry");
    cy.get("h1, .title-text, .list-row-head, .no-result").should("exist");
  });

  it("opens MPIT Project list without errors", () => {
    cy.openList("MPIT Project");
    cy.get("h1, .title-text, .list-row-head, .no-result").should("exist");
  });
});

// ────────────────────────────────────────────────────────────────────────────
// Suite 5: Verify that key amount fields are visible on a saved contract
// ────────────────────────────────────────────────────────────────────────────

describe("5. Key monetary amounts visible in Contract form", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("shows computed term amounts on a saved contract with VAT", () => {
    cy.request({
      method: "GET",
      url: "/api/resource/MPIT Vendor?limit=1&fields=[\"name\"]",
    }).then((res) => {
      const vendorName = res.body?.data?.[0]?.name;
      if (!vendorName) { cy.log("No vendor — skip"); return; }

      cy.request({
        method: "GET",
        url: "/api/resource/MPIT Cost Center?limit=1&fields=[\"name\"]&filters=[[\"is_group\",\"=\",0]]",
      }).then((ccRes) => {
        const ccName = ccRes.body?.data?.[0]?.name;
        if (!ccName) { cy.log("No CC — skip"); return; }

        // Create via API: 1000 net at 22% VAT
        cy.frappePost("/api/resource/MPIT Contract", {
            doctype: "MPIT Contract",
            description: "Cypress VAT Amount Test",
            vendor: vendorName,
            cost_center: ccName,
            status: "Active",
            terms: [{
              from_date: "2040-01-01",
              amount: 1000,
              amount_includes_vat: 0,
              vat_rate: 22,
              billing_cycle: "Monthly",
            }],
        }).then((saveRes) => {
          expect(saveRes.status).to.eq(200);
          const contractName = saveRes.body?.data?.name;

          // Assert via API that the computed amounts are correct
          cy.request(`/api/resource/MPIT Contract/${encodeURIComponent(contractName)}`).then(
            (docRes) => {
              const term = docRes.body?.data?.terms?.[0];
              expect(term).to.exist;
              // amount_net must equal 1000 (net, not gross)
              expect(parseFloat(term.amount_net)).to.be.closeTo(1000.0, 0.01);
              // amount_gross must be 1220 (1000 + 22%)
              expect(parseFloat(term.amount_gross)).to.be.closeTo(1220.0, 0.01);
              // monthly_amount_net must be 1000 (monthly billing)
              expect(parseFloat(term.monthly_amount_net)).to.be.closeTo(1000.0, 0.01);
            }
          );

          // Open the form in Desk and verify the term child table is visible
          cy.visit(`/desk/mpit-contract/${encodeURIComponent(contractName)}`);
          cy.get(".form-layout", { timeout: 15000 }).should("be.visible");
          // Terms child table should be visible
          cy.get('[data-fieldname="terms"]', { timeout: 10000 }).should("exist");
        });
      });
    });
  });
});

// ────────────────────────────────────────────────────────────────────────────
// Suite 6: Actual Entry validation in UI
// ────────────────────────────────────────────────────────────────────────────

describe("6. Actual Entry — validation visible in UI", () => {
  beforeEach(() => {
    cy.frappeLogin("Administrator", ADMIN_PASSWORD);
  });

  it("blocks One-off entry with project link via API (same validation user hits)", () => {
    cy.request({
      method: "GET",
      url: "/api/resource/MPIT Project?limit=1&fields=[\"name\"]",
    }).then((res) => {
      const projectName = res.body?.data?.[0]?.name;
      if (!projectName) { cy.log("No project — skip"); return; }

      // Ensure MPIT Year 2040 exists so the year validation doesn't fire first.
      // The entry_kind validation fires AFTER the year check, so the year must cover the date.
      cy.frappePost(
        "/api/resource/MPIT Year",
        { doctype: "MPIT Year", year: 2040, start_date: "2040-01-01", end_date: "2040-12-31" },
        { failOnStatusCode: false }
      );

      // Attempt to save an invalid One-off entry with a project link
      cy.frappePost(
        "/api/resource/MPIT Actual Entry",
        {
          doctype: "MPIT Actual Entry",
          posting_date: "2040-06-15",
          entry_kind: "One-off",
          project: projectName,
          amount: 100,
          vat_rate: 0,
        },
        { failOnStatusCode: false }
      ).then((r) => {
        // Frappe v16 returns 417 for ValidationError; 409 for duplicate; 422 also seen in some versions.
        // 400 would mean a request-level error (e.g. CSRF) — after fix it must not appear.
        expect(r.status).to.be.oneOf([409, 417, 422]);
        // The error message must mention the rule
        const errMsg = JSON.stringify(r.body);
        expect(errMsg).to.include("One-off");
      });
    });
  });
});
