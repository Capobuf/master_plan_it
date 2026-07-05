// Cypress custom commands for Frappe Desk

/**
 * Login to Frappe Desk via the login form.
 * Frappe redirects to /app after successful login.
 */
Cypress.Commands.add("frappeLogin", (user, password) => {
  const loginUser = user || Cypress.env("FRAPPE_USER") || "Administrator";
  const loginPassword = password || Cypress.env("FRAPPE_PASSWORD");

  if (!loginPassword) {
    throw new Error("CYPRESS_FRAPPE_PASSWORD is required for Cypress Frappe login.");
  }

  cy.request({
    method: "POST",
    url: "/api/method/login",
    form: true,
    body: { usr: loginUser, pwd: loginPassword },
    failOnStatusCode: false,
    log: false,
  }).then((response) => {
    if (response.status !== 200 || response.body?.message !== "Logged In") {
      throw new Error(
        `Cypress Frappe auth preflight failed for ${loginUser}. ` +
          "Run the development setup with CYPRESS_FRAPPE_USER and CYPRESS_FRAPPE_PASSWORD."
      );
    }
    cy.clearCookies({ log: false });
  });

  cy.session(
    [loginUser, loginPassword],
    () => {
      cy.visit("/login");
      // Frappe v16 login page: #login_email / #login_password / button.btn-login
      cy.get("#login_email", { timeout: 15000 }).clear().type(loginUser);
      cy.get("#login_password").clear().type(loginPassword);
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

/**
 * Open a Frappe query-report page by its exact report name.
 * Frappe v16 report route: /app/query-report/{encoded-name}
 */
Cypress.Commands.add("openReport", (reportName) => {
  cy.visit(`/app/query-report/${encodeURIComponent(reportName)}`);
  // Wait for the page wrapper rendered by Frappe before filter form is wired
  cy.get(".page-head, .page-wrapper", { timeout: 20000 }).should("exist");
});

/**
 * Retrieve the Frappe CSRF token from the current session.
 *
 * Frappe v16 embeds `frappe.csrf_token = "<token>"` in the /desk page HTML.
 * All POST/PUT/DELETE REST API calls must include it as X-Frappe-CSRF-Token.
 * cy.request() is a plain HTTP client and does NOT set this header automatically.
 */
Cypress.Commands.add("getFrappeCsrfToken", () => {
  return cy.request("/desk").then((res) => {
    const match = res.body.match(/frappe\.csrf_token\s*=\s*"([^"]+)"/);
    if (!match) {
      throw new Error("Could not extract frappe.csrf_token from /desk response — are you logged in?");
    }
    return match[1];
  });
});

/**
 * POST to a Frappe REST API endpoint with the CSRF token automatically included.
 *
 * Use this instead of cy.request({ method: 'POST', ... }) for all write operations
 * to avoid CSRFTokenError (HTTP 400) that Frappe v16 returns for unauthenticated writes.
 *
 * @param {string} url      - Full path, e.g. "/api/resource/MPIT Contract"
 * @param {object} body     - Request body (will be JSON-serialised)
 * @param {object} options  - Extra cy.request() options (e.g. failOnStatusCode: false)
 */
Cypress.Commands.add("frappePost", (url, body, options = {}) => {
  return cy.getFrappeCsrfToken().then((token) => {
    const { headers: extraHeaders, ...rest } = options;
    return cy.request({
      method: "POST",
      url,
      body,
      headers: {
        "X-Frappe-CSRF-Token": token,
        "Content-Type": "application/json",
        ...extraHeaders,
      },
      ...rest,
    });
  });
});

Cypress.Commands.add("frappeDeleteResource", (doctype, name, options = {}) => {
  return cy.getFrappeCsrfToken().then((token) => {
    const { headers: extraHeaders, ...rest } = options;
    return cy.request({
      method: "DELETE",
      url: `/api/resource/${encodeURIComponent(doctype)}/${encodeURIComponent(name)}`,
      headers: {
        "X-Frappe-CSRF-Token": token,
        ...extraHeaders,
      },
      ...rest,
    });
  });
});
