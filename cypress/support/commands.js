// Cypress custom commands for Frappe Desk

/**
 * Login to Frappe Desk via the login form.
 * Frappe redirects to /app after successful login.
 */
Cypress.Commands.add("frappeLogin", (user = "Administrator", password = "admin") => {
  cy.session(
    [user, password],
    () => {
      cy.visit("/login");
      // Frappe v16 login page: #login_email / #login_password / button.btn-login
      cy.get("#login_email", { timeout: 15000 }).clear().type(user);
      cy.get("#login_password").clear().type(password);
      cy.get("button.btn-login").first().click();
      // Wait for redirect to /app after successful login
      cy.url({ timeout: 30000 }).should("match", /\/(app|desk)/);
      cy.get(".navbar", { timeout: 15000 }).should("be.visible");
    },
    {
      validate() {
        cy.request("/api/method/frappe.auth.get_logged_user").its("status").should("eq", 200);
      },
    }
  );
});

/**
 * Navigate to the Frappe Desk app home.
 */
Cypress.Commands.add("goToDesk", () => {
  cy.visit("/desk");
  cy.get(".navbar", { timeout: 15000 }).should("be.visible");
});

/**
 * Open a DocType list view.
 */
Cypress.Commands.add("openList", (doctype) => {
  const slug = doctype.toLowerCase().replace(/\s+/g, "-");
  cy.visit(`/desk/${slug}`);
  cy.get(".list-row, .result-list, .no-result, h1", { timeout: 15000 }).should("exist");
});

/**
 * Click "New" to open a new document form.
 */
Cypress.Commands.add("newDocument", () => {
  cy.get('button:contains("New"), a:contains("New"), .btn-primary:contains("New")')
    .first()
    .click();
  cy.get(".form-layout, .form-page", { timeout: 10000 }).should("be.visible");
});

/**
 * Assert that a Frappe toast/alert error is visible containing the given text.
 */
Cypress.Commands.add("assertFrappeError", (text) => {
  cy.get(
    '.msgprint-dialog .modal-body, .alert-message, .frappe-alert-danger, .notification-body',
    { timeout: 10000 }
  )
    .should("be.visible")
    .and("contain.text", text);
});

/**
 * Set a value in a Frappe form field by fieldname.
 * Works for Data, Link, Currency, and Select fields.
 */
Cypress.Commands.add("setField", (fieldname, value) => {
  cy.get(`[data-fieldname="${fieldname}"] input, [data-fieldname="${fieldname}"] select`)
    .first()
    .clear()
    .type(String(value));
});
