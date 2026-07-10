/**
 * v16_report_economic_position_smoke.cy.js
 *
 * Smoke tests for the MPIT Economic Position report on Frappe v16.
 */

describe("v16 - Report: MPIT Economic Position", () => {
  beforeEach(() => {
    cy.frappeLogin();
  });

  it("report page loads and displays correct title", () => {
    cy.openReport("MPIT Economic Position");
    cy.contains("MPIT Economic Position", { timeout: 15000 }).should("be.visible");
  });

  it("core visible filters are rendered", () => {
    cy.openReport("MPIT Economic Position");

    [
      "year",
      "cost_center",
      "group_by",
      "basis",
      "scope",
      "hide_zero_rows",
    ].forEach((fieldname) => {
      cy.get(`[data-fieldname="${fieldname}"]`, { timeout: 15000 }).should("be.visible");
    });

    [
      "show_only_exceptions",
      "warning_threshold_percent",
      "print_profile",
      "print_orientation",
      "print_density",
    ].forEach((fieldname) => {
      cy.get(`[data-fieldname="${fieldname}"]`).should("not.exist");
    });
  });

  it("custom PDF template renders with the context provided by Frappe", () => {
    cy.openReport("MPIT Economic Position");
    cy.window({ timeout: 20000 }).its("frappe.query_report.data").should("have.length.greaterThan", 0);

    cy.window().then(async (win) => {
      const report = win.frappe.query_report;
      const printSettings = {
        orientation: "Portrait",
        columns: [],
        include_filters: true,
        with_letter_head: false,
      };
      const customFormat = await report.get_custom_format(printSettings);
      const template = report.get_print_template(printSettings, customFormat);
      const content = win.frappe.render_template(template, {
        title: win.__(report.report_name),
        subtitle: report.get_filters_html_for_print(),
        filters: report.get_filter_values(),
        data: report.get_data_for_print(),
        original_data: report.data,
        columns: report.get_columns_for_print(printSettings, customFormat),
        report,
        print_settings: printSettings,
      });

      expect(content).to.contain("<table>");
      expect(content).to.contain('class="report-title"');
    });
  });

  it("realtime uses the public origin instead of exposing the internal Socket.IO port", () => {
    cy.openReport("MPIT Economic Position");
    cy.window({ timeout: 20000 }).then((win) => {
      expect(win.dev_server).not.to.eq(true);
      expect(win.frappe.realtime.socket.io.uri).to.eq(win.location.origin);
    });

    cy.location("hostname").then((hostname) => {
      if (hostname === "budget.nirolabel.it") {
        cy.window({ timeout: 20000 }).its("frappe.realtime.socket.connected").should("eq", true);
      }
    });
  });
});
