// Cypress custom commands for Frappe Desk

function doctypeSlug(doctype) {
  return doctype.toLowerCase().replace(/\s+/g, "-");
}

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
 * Open the standard Frappe "new document" form for a DocType.
 *
 * The tests use this before setting values through cur_frm. That keeps the
 * browser flow close to what a user does (route, form boot, client scripts,
 * save lifecycle) without making the suite depend on Frappe's frequently
 * changing autocomplete and grid DOM internals.
 */
Cypress.Commands.add("openNewDoc", (doctype) => {
  const slug = doctypeSlug(doctype);
  cy.visit(`/app/${slug}/new-${slug}`);
  cy.get(".form-layout, .form-page", { timeout: 20000 }).should("be.visible");
  cy.window({ timeout: 20000 }).its("cur_frm.doc.doctype").should("eq", doctype);
});

/**
 * Mutate and save the currently opened Frappe form through the browser form
 * controller.
 *
 * This intentionally runs inside the Desk page, not through the REST API. It
 * exercises client-side form setup, field dependencies, child-table handling,
 * server validation, autoname, and the normal save path while avoiding brittle
 * low-level selectors for dynamic Frappe controls.
 */
Cypress.Commands.add("saveCurrentForm", (mutateForm) => {
  return cy.window({ timeout: 20000 }).then({ timeout: 60000 }, async (win) => {
    const frm = win.cur_frm;
    if (!frm) {
      throw new Error("No active Frappe form found on the current page.");
    }

    await mutateForm(win, frm);
    await frm.save();

    return {
      doctype: frm.doc.doctype,
      name: frm.doc.name,
      doc: JSON.parse(JSON.stringify(frm.doc)),
    };
  });
});

/**
 * Save the current form through Frappe Desk's own savedocs endpoint.
 *
 * This is reserved for complex child-table setup where Cypress should still
 * open and populate the real Desk form, but where driving Frappe grid internals
 * or waiting on frm.save() would make the test about framework mechanics
 * instead of the MPIT workflow. The server path is the same endpoint used by
 * Desk form saves.
 */
Cypress.Commands.add("saveCurrentFormViaDesk", (mutateForm) => {
  return cy.window({ timeout: 20000 }).then({ timeout: 60000 }, async (win) => {
    const frm = win.cur_frm;
    if (!frm) {
      throw new Error("No active Frappe form found on the current page.");
    }

    await mutateForm(win, frm);
    let response;
    try {
      response = await win.frappe.call({
        method: "frappe.desk.form.save.savedocs",
        args: {
          doc: JSON.stringify(frm.doc),
          action: "Save",
        },
      });
    } catch (error) {
      const serverMessage = error?.responseJSON?._server_messages || error?.responseText || error?.message;
      throw new Error(`Frappe Desk save failed: ${serverMessage || "unknown error"}`);
    }
    const saved = response.message || frm.doc;

    return {
      doctype: saved.doctype,
      name: saved.name,
      doc: JSON.parse(JSON.stringify(saved)),
    };
  });
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

/**
 * PUT to a Frappe REST API endpoint with CSRF included.
 *
 * Economic tests mostly create data through the UI and verify through reports.
 * PUT remains useful for targeted setup/teardown where driving a full form
 * would add noise without improving behavioral coverage.
 */
Cypress.Commands.add("frappePut", (url, body, options = {}) => {
  return cy.getFrappeCsrfToken().then((token) => {
    const { headers: extraHeaders, ...rest } = options;
    return cy.request({
      method: "PUT",
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

/**
 * Execute a Frappe query report and return the raw server response.
 *
 * Numeric assertions use this path instead of scraping the datatable because
 * the report endpoint is the economic contract: it returns the exact data,
 * chart, and summary used by Desk after filters are applied.
 */
Cypress.Commands.add("runReport", (reportName, filters = {}) => {
  return cy.frappePost("/api/method/frappe.desk.query_report.run", {
    report_name: reportName,
    filters: JSON.stringify(filters),
    ignore_prepared_report: 1,
  }).then((response) => {
    expect(response.status, `${reportName} report status`).to.eq(200);
    expect(response.body?.message, `${reportName} report payload`).to.be.an("object");
    return response.body.message;
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
