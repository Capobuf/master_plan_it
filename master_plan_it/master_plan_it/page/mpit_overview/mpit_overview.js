frappe.pages["mpit-overview"].on_page_load = function (wrapper) {
    wrapper.mpit_overview_page = new MPITOverviewPage(wrapper);
};

frappe.pages["mpit-overview"].on_page_show = function (wrapper) {
    if (wrapper.mpit_overview_page) {
        wrapper.mpit_overview_page.on_page_show();
    }
};

class MPITOverviewPage {
    constructor(wrapper) {
        this.wrapper = wrapper;
        this.page = frappe.ui.make_app_page({
            parent: wrapper,
            title: __("Panoramica MPIT"),
            single_column: true,
        });

        this.state = {
            filters: {
                year: null,
                cost_center: null,
                section_scope: "All",
                expense_phase: "All",
                contract: null,
                project: null,
                vendor: null,
                show_zero_rows: 0,
            },
            payload: null,
            lines_loaded: false,
            request_id: 0,
        };
        this.controls = {};
        this.is_syncing_controls = false;

        this._setup_header();
        this._build_layout();
        this._bind_events();
        this._load_data({ include_lines: false });
    }

    on_page_show() {
        if (!this.state.payload) {
            this._load_data({ include_lines: false });
        }
    }

    _setup_header() {
        this.controls.year = this.page.add_field({
            fieldname: "year",
            label: __("Year"),
            fieldtype: "Link",
            options: "MPIT Year",
            reqd: 1,
            change: () => this._on_core_filter_change("year", this.controls.year.get_value()),
        });

        this.controls.cost_center = this.page.add_field({
            fieldname: "cost_center",
            label: __("Cost Center"),
            fieldtype: "Link",
            options: "MPIT Cost Center",
            change: () => this._on_core_filter_change("cost_center", this.controls.cost_center.get_value()),
        });

        this.controls.section_scope = this.page.add_field({
            fieldname: "section_scope",
            label: __("Scope"),
            fieldtype: "Select",
            options: "All\nContracts\nExpenses\nPlafond",
            default: "All",
            change: () =>
                this._on_core_filter_change("section_scope", this.controls.section_scope.get_value() || "All"),
        });

        this.page.set_primary_action(__("Refresh"), () => {
            this._load_data({ include_lines: this.state.lines_loaded });
        });

        this.page.set_secondary_action(__("Advanced Filters"), async () => {
            await this._open_advanced_filters_dialog();
        });

        this.page.add_menu_item(__("Open Legacy Report"), () => {
            this._open_legacy_report();
        });
        this.page.add_menu_item(__("Reset Filters"), () => {
            this._reset_filters();
        });
    }

    _build_layout() {
        this.$container = $('<div class="pt-3 pb-5"></div>').appendTo(this.page.main);
        this.$filters = $('<div class="mb-3"></div>').appendTo(this.$container);
        this.$kpis = $('<div class="mb-4"></div>').appendTo(this.$container);
        this.$overview = $('<div class="mb-4"></div>').appendTo(this.$container);
        this.$buildup = $('<div class="mb-4"></div>').appendTo(this.$container);
        this.$lines = $('<div class="mb-4"></div>').appendTo(this.$container);
    }

    _bind_events() {
        this.$container.on("click", ".mpit-filter-cost-center", (event) => {
            event.preventDefault();
            const costCenter = $(event.currentTarget).data("costCenter");
            if (!costCenter) return;
            this.state.filters.cost_center = String(costCenter);
            this.state.lines_loaded = false;
            this._sync_controls_from_state();
            this._load_data({ include_lines: false });
        });

        this.$container.on("click", ".mpit-clear-cost-center", (event) => {
            event.preventDefault();
            this.state.filters.cost_center = null;
            this.state.lines_loaded = false;
            this._sync_controls_from_state();
            this._load_data({ include_lines: false });
        });

        this.$container.on("click", ".mpit-load-lines", (event) => {
            event.preventDefault();
            this._load_data({ include_lines: true });
        });

        this.$container.on("click", ".mpit-open-doc", (event) => {
            event.preventDefault();
            const doctype = $(event.currentTarget).data("doctype");
            const name = $(event.currentTarget).data("name");
            if (!doctype || !name) return;
            frappe.set_route("Form", String(doctype), String(name));
        });
    }

    _on_core_filter_change(fieldname, value) {
        if (this.is_syncing_controls) return;

        this.state.filters[fieldname] = this._clean_filter_value(value);
        this.state.lines_loaded = false;
        this._load_data({ include_lines: false });
    }

    async _open_advanced_filters_dialog() {
        const values = await frappe.prompt(
            [
                {
                    fieldname: "contract",
                    label: __("Contract"),
                    fieldtype: "Link",
                    options: "MPIT Contract",
                    default: this.state.filters.contract || "",
                },
                {
                    fieldname: "project",
                    label: __("Project"),
                    fieldtype: "Link",
                    options: "MPIT Project",
                    default: this.state.filters.project || "",
                },
                {
                    fieldname: "vendor",
                    label: __("Vendor"),
                    fieldtype: "Link",
                    options: "MPIT Vendor",
                    default: this.state.filters.vendor || "",
                },
                {
                    fieldname: "expense_phase",
                    label: __("Expense Phase"),
                    fieldtype: "Select",
                    options: "All\nEstimate\nQuote\nActual",
                    default: this.state.filters.expense_phase || "All",
                },
                {
                    fieldname: "show_zero_rows",
                    label: __("Show Zero Rows"),
                    fieldtype: "Check",
                    default: this.state.filters.show_zero_rows ? 1 : 0,
                },
            ],
            null,
            __("Advanced Filters"),
            __("Apply")
        );

        if (!values) return;

        this.state.filters.contract = this._clean_filter_value(values.contract);
        this.state.filters.project = this._clean_filter_value(values.project);
        this.state.filters.vendor = this._clean_filter_value(values.vendor);
        this.state.filters.expense_phase = values.expense_phase || "All";
        this.state.filters.show_zero_rows = values.show_zero_rows ? 1 : 0;
        this.state.lines_loaded = false;
        this._load_data({ include_lines: false });
    }

    _reset_filters() {
        this.state.filters = {
            year: this.state.filters.year,
            cost_center: null,
            section_scope: "All",
            expense_phase: "All",
            contract: null,
            project: null,
            vendor: null,
            show_zero_rows: 0,
        };
        this.state.lines_loaded = false;
        this._sync_controls_from_state();
        this._load_data({ include_lines: false });
    }

    _open_legacy_report() {
        frappe.set_route("query-report", "MPIT Overview", {
            year: this.state.filters.year,
            view_mode: "Summary",
            cost_center: this.state.filters.cost_center || "",
            show_zero_rows: this.state.filters.show_zero_rows ? 1 : 0,
        });
    }

    async _load_data({ include_lines = false } = {}) {
        const requestId = ++this.state.request_id;
        this.page.set_indicator(__("Loading"), "orange");

        try {
            const response = await frappe.call({
                method: "master_plan_it.master_plan_it.page.mpit_overview.mpit_overview.get_overview_page_data",
                args: {
                    filters: this.state.filters,
                    include_lines: include_lines ? 1 : 0,
                },
                freeze: false,
            });

            if (requestId !== this.state.request_id) return;

            const payload = response.message || {};
            this.state.payload = payload;
            this.state.lines_loaded = Boolean(payload.lines && payload.lines.loaded);
            this._sync_state_filters(payload.filters || {});
            this._sync_controls_from_state();
            this._render();
            this._sync_page_header();
            this.page.set_indicator(__("Ready"), "green");
        } catch (error) {
            if (requestId !== this.state.request_id) return;
            this.page.set_indicator(__("Error"), "red");
            this.$container.html(
                `<div class="alert alert-danger">${this._escape_html(__("Unable to load overview data."))}</div>`
            );
            throw error;
        }
    }

    _sync_state_filters(serverFilters) {
        const merged = Object.assign({}, this.state.filters, serverFilters || {});
        merged.section_scope = merged.section_scope || "All";
        merged.expense_phase = merged.expense_phase || "All";
        merged.show_zero_rows = merged.show_zero_rows ? 1 : 0;
        merged.year = this._clean_filter_value(merged.year);
        merged.cost_center = this._clean_filter_value(merged.cost_center);
        merged.contract = this._clean_filter_value(merged.contract);
        merged.project = this._clean_filter_value(merged.project);
        merged.vendor = this._clean_filter_value(merged.vendor);
        this.state.filters = merged;
    }

    _sync_controls_from_state() {
        this.is_syncing_controls = true;
        try {
            this.controls.year.set_value(this.state.filters.year || "");
            this.controls.cost_center.set_value(this.state.filters.cost_center || "");
            this.controls.section_scope.set_value(this.state.filters.section_scope || "All");
        } finally {
            this.is_syncing_controls = false;
        }
    }

    _sync_page_header() {
        const year = this.state.filters.year || "-";
        const scope = this.state.filters.section_scope || "All";
        this.page.set_title_sub(__("Year {0} - Scope {1}", [year, scope]));
    }

    _render() {
        const payload = this.state.payload || {};
        const overview = payload.overview || { rows: [], summary: {} };
        const buildup = payload.buildup || { rows: [], summary: {} };
        const lines = payload.lines || { rows: [], summary: {}, loaded: false };
        const kpis = payload.kpis || [];

        this.$filters.html(this._render_filter_summary());
        this.$kpis.html(this._render_kpis(kpis));
        this.$overview.html(this._render_overview_table(overview.rows || [], overview.summary || {}));
        this.$buildup.html(this._render_buildup_section(buildup.rows || []));
        this.$lines.html(this._render_lines_section(lines));
    }

    _render_filter_summary() {
        const filters = [];
        filters.push({ label: __("Year"), value: this.state.filters.year || "-" });
        filters.push({ label: __("Scope"), value: this.state.filters.section_scope || "All" });
        if (this.state.filters.cost_center) {
            filters.push({ label: __("Cost Center"), value: this.state.filters.cost_center });
        }
        if (this.state.filters.contract) {
            filters.push({ label: __("Contract"), value: this.state.filters.contract });
        }
        if (this.state.filters.project) {
            filters.push({ label: __("Project"), value: this.state.filters.project });
        }
        if (this.state.filters.vendor) {
            filters.push({ label: __("Vendor"), value: this.state.filters.vendor });
        }
        if ((this.state.filters.expense_phase || "All") !== "All") {
            filters.push({ label: __("Expense Phase"), value: this.state.filters.expense_phase });
        }
        if (this.state.filters.show_zero_rows) {
            filters.push({ label: __("Zero Rows"), value: __("Shown") });
        }

        const chips = filters
            .map(
                (filter) =>
                    `<span class="badge rounded-pill border text-dark bg-light me-2 mb-2">${this._escape_html(filter.label)}: ${this._escape_html(filter.value)}</span>`
            )
            .join("");

        const clearCostCenter = this.state.filters.cost_center
            ? `<a href="#" class="mpit-clear-cost-center ms-2">${this._escape_html(__("Clear Cost Center"))}</a>`
            : "";

        return `
            <div class="card">
                <div class="card-body">
                    <div class="small text-muted mb-2">${this._escape_html(__("Active Filters"))}</div>
                    <div>${chips}${clearCostCenter}</div>
                </div>
            </div>
        `;
    }

    _render_kpis(kpis) {
        const cards = (kpis || [])
            .map((kpi) => {
                const value = this._to_number(kpi.value);
                const valueClass = this._kpi_value_class(kpi.fieldname, value);
                return `
                    <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="small text-muted">${this._escape_html(kpi.label || "")}</div>
                                <div class="h4 mb-0 ${valueClass}">${this._format_currency(value)}</div>
                            </div>
                        </div>
                    </div>
                `;
            })
            .join("");

        return `
            <section>
                <h4 class="mb-3">${this._escape_html(__("KPI Snapshot"))}</h4>
                <div class="row g-3">${cards || `<div class="col-12 text-muted">${this._escape_html(__("No KPI data."))}</div>`}</div>
            </section>
        `;
    }

    _render_overview_table(rows, summary) {
        if (!rows || !rows.length) {
            return `
                <section class="card">
                    <div class="card-body">
                        <h4 class="mb-2">${this._escape_html(__("Panoramica per centro di costo"))}</h4>
                        <div class="text-muted">${this._escape_html(__("No cost center data for the selected filters."))}</div>
                    </div>
                </section>
            `;
        }

        const body = rows
            .map((row) => {
                const over = this._to_number(row.over);
                const critical = over > 0 ? `<span class="text-danger small fw-bold">${this._escape_html(__("Critical"))}</span>` : "";
                return `
                    <tr>
                        <td>
                            <a href="#" class="mpit-filter-cost-center" data-cost-center="${this._escape_attr(row.cost_center || "")}">
                                ${this._escape_html(row.cost_center || "-")}
                            </a>
                            ${critical}
                        </td>
                        <td class="text-end">${this._format_currency(row.forecast_total)}</td>
                        <td class="text-end">${this._format_currency(row.actual_total)}</td>
                        <td class="text-end">${this._format_currency(row.plafond)}</td>
                        <td class="text-end">${this._format_currency(row.remaining)}</td>
                        <td class="text-end">${this._format_currency(row.over)}</td>
                    </tr>
                `;
            })
            .join("");

        return `
            <section class="card">
                <div class="card-body">
                    <h4 class="mb-3">${this._escape_html(__("Panoramica per centro di costo"))}</h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>${this._escape_html(__("Cost Center"))}</th>
                                    <th class="text-end">${this._escape_html(__("Forecast"))}</th>
                                    <th class="text-end">${this._escape_html(__("Actual"))}</th>
                                    <th class="text-end">${this._escape_html(__("Plafond"))}</th>
                                    <th class="text-end">${this._escape_html(__("Remaining"))}</th>
                                    <th class="text-end">${this._escape_html(__("Over"))}</th>
                                </tr>
                            </thead>
                            <tbody>${body}</tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>${this._escape_html(__("Totals"))}</td>
                                    <td class="text-end">${this._format_currency(summary.forecast_total)}</td>
                                    <td class="text-end">${this._format_currency(summary.actual_total)}</td>
                                    <td class="text-end">${this._format_currency(summary.plafond)}</td>
                                    <td class="text-end">${this._format_currency(summary.remaining)}</td>
                                    <td class="text-end">${this._format_currency(summary.over)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </section>
        `;
    }

    _render_buildup_section(rows) {
        const groups = this._group_buildup_rows(rows || []);
        if (!groups.length) {
            return `
                <section class="card">
                    <div class="card-body">
                        <h4 class="mb-2">${this._escape_html(__("Composizione del totale"))}</h4>
                        <div class="text-muted">${this._escape_html(__("No build-up data for the selected filters."))}</div>
                    </div>
                </section>
            `;
        }

        const cards = groups
            .map((group) => {
                const detailRows = (group.details || [])
                    .map(
                        (row) => `
                            <tr>
                                <td>${this._escape_html(row.cost_center || "-")}</td>
                                <td class="text-end">${this._format_currency(row.forecast_total)}</td>
                                <td class="text-end">${this._format_currency(row.actual_total)}</td>
                                <td class="text-end">${this._format_currency(row.plafond)}</td>
                                <td class="text-end">${this._format_currency(row.remaining)}</td>
                                <td class="text-end">${this._format_currency(row.over)}</td>
                            </tr>
                        `
                    )
                    .join("");

                return `
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <div>
                                <a href="#" class="mpit-filter-cost-center" data-cost-center="${this._escape_attr(group.cost_center || "")}">
                                    ${this._escape_html(group.cost_center || "-")}
                                </a>
                            </div>
                            <div class="small text-muted">
                                ${this._escape_html(__("Forecast"))}: ${this._format_currency(group.header.forecast_total)}
                                | ${this._escape_html(__("Actual"))}: ${this._format_currency(group.header.actual_total)}
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>${this._escape_html(__("Contribution"))}</th>
                                            <th class="text-end">${this._escape_html(__("Forecast"))}</th>
                                            <th class="text-end">${this._escape_html(__("Actual"))}</th>
                                            <th class="text-end">${this._escape_html(__("Plafond"))}</th>
                                            <th class="text-end">${this._escape_html(__("Remaining"))}</th>
                                            <th class="text-end">${this._escape_html(__("Over"))}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${detailRows || `<tr><td colspan="6" class="text-muted">${this._escape_html(__("No detailed contributions."))}</td></tr>`}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            })
            .join("");

        return `
            <section>
                <h4 class="mb-3">${this._escape_html(__("Composizione del totale"))}</h4>
                ${cards}
            </section>
        `;
    }

    _render_lines_section(linesPayload) {
        const loaded = Boolean(linesPayload.loaded);
        const rows = linesPayload.rows || [];
        const summary = linesPayload.summary || {};

        if (!loaded) {
            return `
                <section class="card">
                    <div class="card-body">
                        <h4 class="mb-2">${this._escape_html(__("Righe operative"))}</h4>
                        <p class="text-muted mb-3">${this._escape_html(__("Load operational rows only when needed to keep the page responsive."))}</p>
                        <button class="btn btn-sm btn-primary mpit-load-lines">${this._escape_html(__("Load Operational Rows"))}</button>
                    </div>
                </section>
            `;
        }

        const rowHtml = rows
            .map((row) => {
                const sourceTarget = this._source_target(row);
                const sourceDoc = sourceTarget
                    ? `<a href="#" class="mpit-open-doc" data-doctype="${this._escape_attr(sourceTarget.doctype)}" data-name="${this._escape_attr(sourceTarget.name)}">${this._escape_html(row.source_document || "-")}</a>`
                    : this._escape_html(row.source_document || "-");

                const contractLink = row.contract
                    ? `<a href="#" class="mpit-open-doc" data-doctype="MPIT Contract" data-name="${this._escape_attr(row.contract)}">${this._escape_html(row.contract)}</a>`
                    : `<span class="text-muted">-</span>`;
                const projectLink = row.project
                    ? `<a href="#" class="mpit-open-doc" data-doctype="MPIT Project" data-name="${this._escape_attr(row.project)}">${this._escape_html(row.project)}</a>`
                    : `<span class="text-muted">-</span>`;

                const vendorLink = row.vendor
                    ? `<a href="#" class="mpit-open-doc" data-doctype="MPIT Vendor" data-name="${this._escape_attr(row.vendor)}">${this._escape_html(row.vendor)}</a>`
                    : `<span class="text-muted">-</span>`;

                const secondaryParts = [
                    `${this._escape_html(__("Funding"))}: ${this._escape_html(row.funding || "-")}`,
                    `${this._escape_html(__("Period"))}: ${this._format_date(row.period_start)} - ${this._format_date(row.period_end)}`,
                    `${this._escape_html(__("Spend Date"))}: ${this._format_date(row.spend_date)}`,
                ];

                return `
                    <tr>
                        <td>
                            <a href="#" class="mpit-filter-cost-center" data-cost-center="${this._escape_attr(row.cost_center || "")}">
                                ${this._escape_html(row.cost_center || "-")}
                            </a>
                        </td>
                        <td>
                            <div>${this._escape_html(row.source_type || "-")}</div>
                            <div class="small text-muted">${this._escape_html(row.source_row || "-")}</div>
                        </td>
                        <td>${sourceDoc}</td>
                        <td>
                            <div>${projectLink}</div>
                            <div class="small text-muted">${contractLink}</div>
                        </td>
                        <td>${vendorLink}</td>
                        <td>${this._escape_html(row.expense_phase || "-")}</td>
                        <td class="text-end">${this._format_currency(row.amount_net)}</td>
                        <td class="text-end fw-bold">${this._format_currency(row.annual_contribution_net)}</td>
                        <td>${this._escape_html(row.logical_state || "-")}</td>
                        <td class="small text-muted">${secondaryParts.join("<br>")}</td>
                    </tr>
                `;
            })
            .join("");

        return `
            <section class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">${this._escape_html(__("Righe operative"))}</h4>
                        <div>
                            <span class="me-3 small text-muted">${this._escape_html(__("Rows"))}: ${this._escape_html(summary.total_lines || 0)}</span>
                            <span class="me-3 small text-muted">${this._escape_html(__("Annual Total"))}: ${this._format_currency(summary.annual_total)}</span>
                            <button class="btn btn-sm btn-outline-primary mpit-load-lines">${this._escape_html(__("Refresh Rows"))}</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>${this._escape_html(__("Cost Center"))}</th>
                                    <th>${this._escape_html(__("Source Type"))}</th>
                                    <th>${this._escape_html(__("Source Document"))}</th>
                                    <th>${this._escape_html(__("Project / Contract"))}</th>
                                    <th>${this._escape_html(__("Vendor"))}</th>
                                    <th>${this._escape_html(__("Phase"))}</th>
                                    <th class="text-end">${this._escape_html(__("Amount Net"))}</th>
                                    <th class="text-end">${this._escape_html(__("Annual Contribution"))}</th>
                                    <th>${this._escape_html(__("State"))}</th>
                                    <th>${this._escape_html(__("Secondary"))}</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowHtml || `<tr><td colspan="10" class="text-muted">${this._escape_html(__("No operational rows for the selected filters."))}</td></tr>`}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        `;
    }

    _group_buildup_rows(rows) {
        const groups = [];
        let current = null;

        (rows || []).forEach((row) => {
            const indent = Number(row.indent || 0);
            if (indent === 0) {
                current = {
                    cost_center: row.cost_center || "",
                    header: row,
                    details: [],
                };
                groups.push(current);
                return;
            }
            if (current) {
                current.details.push(row);
            }
        });

        return groups;
    }

    _source_target(row) {
        const sourceType = String(row.source_type || "");
        if (!row.source_document) return null;
        if (sourceType === "Contract Term" || sourceType === "Contract Header") {
            return { doctype: "MPIT Contract", name: row.source_document };
        }
        if (sourceType === "Expense Row" || sourceType === "Plafond") {
            return { doctype: "MPIT Expense", name: row.source_document };
        }
        return null;
    }

    _kpi_value_class(fieldname, value) {
        if (fieldname === "over" && value > 0) return "text-danger";
        if (fieldname === "remaining" && value > 0) return "text-success";
        return "";
    }

    _to_number(value) {
        const number = Number(value || 0);
        return Number.isFinite(number) ? number : 0;
    }

    _format_currency(value) {
        return frappe.format(this._to_number(value), { fieldtype: "Currency" }, { always_show_decimals: true });
    }

    _format_date(value) {
        if (!value) return "-";
        try {
            return frappe.datetime.str_to_user(value);
        } catch (error) {
            return this._escape_html(String(value));
        }
    }

    _clean_filter_value(value) {
        if (value === null || value === undefined || value === "") return null;
        const cleaned = String(value).trim();
        return cleaned || null;
    }

    _escape_html(value) {
        return frappe.utils.escape_html(String(value ?? ""));
    }

    _escape_attr(value) {
        return this._escape_html(value).replace(/"/g, "&quot;");
    }
}

