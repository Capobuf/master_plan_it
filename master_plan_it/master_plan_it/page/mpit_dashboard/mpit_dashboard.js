frappe.provide("master_plan_it.dashboard");

frappe.pages["mpit-dashboard"].on_page_load = async function (wrapper) {
	const page = frappe.ui.make_app_page({
		parent: wrapper,
		title: __("Master Plan IT — Overview"),
		single_column: true,
	});

	const state = {
		page,
		filters: {},
		charts: {},
		controls: {},
	};

	build_layout(page, state);
	await init_filters(state);
	// Suppress the initial "Please select a Year." dialog if user hasn't configured a default.
	refresh_all(state, true);
};

function build_layout(page, state) {
	const $main = $(page.main);
	$main.empty().addClass("pb-5");

	// Header
	const $header = $(`
		<div class="d-flex justify-content-between align-items-center mb-3">
			<div>
				<h2 class="mb-1">${__("Master Plan IT — Overview")}</h2>
				<div class="text-muted" data-role="mpit-subtitle"></div>
			</div>
			<div class="btn-group" role="group">
				<button class="btn btn-sm btn-secondary" data-action="open-plan-report">${__("Plan vs Cap vs Actual")}</button>
				<button class="btn btn-sm btn-secondary" data-action="open-monthly-report">${__("Monthly Plan v3")}</button>
				<button class="btn btn-sm btn-secondary" data-action="open-renewals-report">${__("Renewals Window")}</button>
			</div>
		</div>
	`);
	$header.find('[data-action="open-plan-report"]').on("click", () =>
		frappe.set_route("query-report", "MPIT Plan vs Cap vs Actual")
	);
	$header.find('[data-action="open-monthly-report"]').on("click", () =>
		frappe.set_route("query-report", "MPIT Monthly Plan v3")
	);
	$header.find('[data-action="open-renewals-report"]').on("click", () =>
		frappe.set_route("query-report", "MPIT Renewals Window")
	);
	$main.append($header);

	// Filter bar
	const $filter_bar = $(
		'<div class="border rounded mb-3 p-3 bg-light" style="position: sticky; top: 60px; z-index: 1;"></div>'
	);
	const $filter_row = $('<div class="row gy-2"></div>').appendTo($filter_bar);
	state.controls.year = frappe.ui.form.make_control({
		df: {
			fieldtype: "Link",
			label: __("Year"),
			fieldname: "year",
			options: "MPIT Year",
			reqd: 1,
		},
		parent: $('<div class="col-sm-3"></div>').appendTo($filter_row),
		render_input: true,
	});
	state.controls.cost_center = frappe.ui.form.make_control({
		df: {
			fieldtype: "Link",
			label: __("Cost Center"),
			fieldname: "cost_center",
			options: "MPIT Cost Center",
		},
		parent: $('<div class="col-sm-3"></div>').appendTo($filter_row),
		render_input: true,
	});
	state.controls.include_children = frappe.ui.form.make_control({
		df: {
			fieldtype: "Check",
			label: __("Include Children"),
			fieldname: "include_children",
		},
		parent: $('<div class="col-sm-2"></div>').appendTo($filter_row),
		render_input: true,
	});
	const $refresh_col = $('<div class="col-sm-2 d-flex align-items-end"></div>').appendTo($filter_row);
	const $refresh_btn = $('<button class="btn btn-primary w-100">' + __("Refresh") + "</button>");
	$refresh_btn.on("click", () => refresh_all(state));
	$refresh_col.append($refresh_btn);

	$main.append($filter_bar);
	state.$subtitle = $header.find('[data-role="mpit-subtitle"]');

	// KPI strip
	state.$kpi = $('<div class="row gy-2 mb-3" data-role="kpis"></div>');
	$main.append(state.$kpi);

	// Main charts
	state.$main_charts = $(`
		<div class="row gy-3 mb-3">
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body" data-role="chart-monthly"></div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body" data-role="chart-cap"></div>
				</div>
			</div>
		</div>
	`);
	$main.append(state.$main_charts);

	// Secondary charts
	state.$secondary = $(`
		<div class="row gy-3 mb-3">
			<div class="col-md-4">
				<div class="card h-100">
					<div class="card-body" data-role="chart-kind"></div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card h-100">
					<div class="card-body" data-role="chart-contracts"></div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card h-100">
					<div class="card-body" data-role="chart-projects"></div>
				</div>
			</div>
		</div>
	`);
	$main.append(state.$secondary);

	// Worklists
	state.$worklists = $(`
		<div class="row gy-3 mb-5">
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body" data-role="table-overcap"></div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body" data-role="table-renewals"></div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body" data-role="table-planned"></div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body" data-role="table-actuals"></div>
				</div>
			</div>
		</div>
	`);
	$main.append(state.$worklists);

	// Bind filter changes with debounce
	let debounce_timer = null;
	const trigger_refresh = () => {
		clearTimeout(debounce_timer);
		debounce_timer = setTimeout(() => refresh_all(state), 300);
	};
	state.controls.year.df.onchange = () => refresh_all(state);
	state.controls.cost_center.df.onchange = () => {
		toggle_include_children(state);
		trigger_refresh();
	};
	state.controls.include_children.df.onchange = () => trigger_refresh();
}

async function init_filters(state) {
	const stored = load_filters();
	const default_year = stored.year || (await resolve_default_year());
	state.controls.year.set_value(default_year || "");
	state.controls.cost_center.set_value(stored.cost_center || "");
	state.controls.include_children.set_value(stored.include_children || 0);
	toggle_include_children(state);
}

function toggle_include_children(state) {
	const has_cc = !!state.controls.cost_center.get_value();
	state.controls.include_children.$wrapper.toggle(has_cc);
}

function get_filter_payload(state) {
	return {
		year: state.controls.year.get_value(),
		cost_center: state.controls.cost_center.get_value(),
		include_children: state.controls.include_children.get_value() ? 1 : 0,
	};
}

async function resolve_default_year() {
	const user_default = frappe.defaults.get_user_default("fiscal_year");
	if (user_default) return user_default;

	const active = await frappe.db.get_value("MPIT Year", { is_active: 1 }, "name", "year desc");
	if (active && active.message && active.message.name) return active.message.name;

	const latest = await frappe.db.get_value("MPIT Year", {}, "name", "year desc");
	return (latest && latest.message && latest.message.name) || "";
}

function save_filters(filters) {
	localStorage.setItem("mpit.dashboard.filters", JSON.stringify(filters));
}

function load_filters() {
	try {
		const raw = localStorage.getItem("mpit.dashboard.filters");
		return raw ? JSON.parse(raw) : {};
	} catch (e) {
		return {};
	}
}

function set_subtitle(state, filters) {
	let parts = [`${__("Year")}: ${filters.year || "-"}`];
	if (filters.cost_center) {
		parts.push(`${__("Cost Center")}: ${filters.cost_center}${filters.include_children ? " (+children)" : ""}`);
	}
	state.$subtitle.text(parts.join(" · "));
}

function apply_chart_overrides(payload, overrides) {
	if (!payload || !overrides) return payload;
	return Object.assign({}, payload, overrides);
}

async function refresh_all(state, suppress_no_year_msg = false) {
	const filters = get_filter_payload(state);
	if (!filters.year) {
		// During initial page load we don't want an intrusive modal. Just set subtitle and return silently.
		if (suppress_no_year_msg) {
			set_subtitle(state, filters);
			// show empty placeholders
			show_loading(state);
			return;
		}
		frappe.msgprint(__("Please select a Year."));
		return;
	}

	save_filters(filters);
	set_subtitle(state, filters);
	show_loading(state);

	try {
		const overview = await frappe.call({
			method: "master_plan_it.master_plan_it.api.dashboard.get_overview",
			args: { filters_json: filters },
		});
		render_kpis(state, overview.message.kpis || []);
		const monthly_payload = apply_chart_overrides(overview.message.charts.monthly_plan_vs_actual, {
			type: "line",
			lineOptions: { regionFill: 1 },
		});
		render_chart(
			state.$main_charts.find('[data-role="chart-monthly"]'),
			__("Monthly Plan vs Actual (NET)"),
			monthly_payload
		);
		render_chart(
			state.$main_charts.find('[data-role="chart-cap"]'),
			__("Cap vs Actual by Cost Center"),
			overview.message.charts.cap_vs_actual_by_cost_center
		);
	} catch (e) {
		console.error(e);
		frappe.msgprint(__("Failed to load overview."));
	}

	frappe.call({
		method: "master_plan_it.master_plan_it.api.dashboard.get_secondary",
		args: { filters_json: filters },
		callback: (r) => {
			if (!r.message) return;
			render_chart(
				state.$secondary.find('[data-role="chart-kind"]'),
				__("Actual Entries by Kind"),
				apply_chart_overrides(r.message.actual_by_kind, { type: "pie" })
			);
			render_chart(
				state.$secondary.find('[data-role="chart-contracts"]'),
				__("Contracts by Status"),
				apply_chart_overrides(r.message.contracts_by_status, { type: "percentage" })
			);
			render_chart(
				state.$secondary.find('[data-role="chart-projects"]'),
				__("Projects by Status"),
				apply_chart_overrides(r.message.projects_by_status, { type: "percentage" })
			);
		},
	});

	frappe.call({
		method: "master_plan_it.master_plan_it.api.dashboard.get_worklists",
		args: { filters_json: filters },
		callback: (r) => {
			if (!r.message) return;
			render_table(state.$worklists.find('[data-role="table-overcap"]'), __("Over Cap Cost Centers"), r.message.over_cap_cost_centers);
			render_table(state.$worklists.find('[data-role="table-renewals"]'), __("Upcoming Renewals"), r.message.renewals);
			render_table(
				state.$worklists.find('[data-role="table-planned"]'),
				__("Planned Exceptions / Out of Horizon"),
				r.message.planned_exceptions
			);
			render_table(
				state.$worklists.find('[data-role="table-actuals"]'),
				__("Latest Actual Entries"),
				r.message.latest_actual_entries
			);
		},
	});
}

function show_loading(state) {
	state.$kpi.empty().append(`<div class="text-muted">${__("Loading…")}</div>`);
	[state.$main_charts, state.$secondary, state.$worklists].forEach(($el) =>
		$el.find("[data-role]").each(function () {
			$(this).empty().append(`<div class="text-muted">${__("Loading…")}</div>`);
		})
	);
}

function render_kpis(state, kpis) {
	state.$kpi.empty();
	if (!kpis || !kpis.length) {
		state.$kpi.append(`<div class="text-muted">${__("No KPIs found")}</div>`);
		return;
	}
	kpis.forEach((kpi) => {
		const $col = $('<div class="col-md-3 col-sm-6"></div>');
		const $card = $(`
			<div class="card h-100 cursor-pointer">
				<div class="card-body">
					<div class="text-muted text-uppercase small mb-1">${frappe.utils.escape_html(kpi.label || "")}</div>
					<div class="h4 mb-1">${kpi.value === 'N/A' || (kpi.extra && kpi.extra._error) ? __('N/A') : (kpi.value !== undefined ? frappe.format(kpi.value, { fieldtype: "Currency" }) : "-")}</div>
					<div class="text-muted small">${frappe.utils.escape_html(kpi.subtitle || "")}</div>
				</div>
			</div>
		`);
		if (kpi.route_options) {
			$card.on("click", () => apply_route(kpi.route_options));
		}
		$col.append($card);
		state.$kpi.append($col);
	});
}

function apply_route(route_options) {
	if (!route_options) return;
	if (route_options.report) {
		frappe.set_route("query-report", route_options.report, route_options.filters || {});
		return;
	}
	if (route_options.doctype) {
		frappe.set_route("List", route_options.doctype, route_options.filters || {});
		return;
	}
}

function hasChartData(payload) {
	if (!payload) return false;
	if (!payload.datasets || !payload.datasets.length) return false;
	for (const ds of payload.datasets) {
		const vals = ds.values || ds.data || [];
		if (!Array.isArray(vals)) continue;
		if (vals.length === 0) continue;
		// If at least one dataset has any value (including zeros), consider it data
		return true;
	}
	return false;
}

function render_chart($target, title, payload) {
	$target.empty();
	const $title = $(`<div class="fw-bold mb-1">${frappe.utils.escape_html(title)}</div>`);
	$target.append($title);

	if (!payload) {
		$target.append(`<div class="text-muted">${__("No data")}</div>`);
		return;
	}

	if (payload._error) {
		$target.append(`<div class="text-muted">${__("N/A")}</div>`);
		console.warn("Chart source error:", payload._error);
		return;
	}

	if (!payload.labels || !hasChartData(payload)) {
		$target.append(`<div class="text-muted">${__("No data")}</div>`);
		return;
	}

	const $chart = $('<div style="min-height: 280px;"></div>');
	$target.append($chart);

	const typeRaw = payload.type || payload.chart_type || "bar";
	const type = (typeof typeRaw === 'string') ? typeRaw.toLowerCase() : typeRaw;

	const chartOptions = {
		title: "",
		data: {
			labels: payload.labels,
			datasets: payload.datasets,
		},
		type: type,
		height: payload.height || 280,
		colors: payload.colors || ["#5E64FF", "#FF5858", "#7CD6FD"],
		barOptions: payload.barOptions || payload.bar_options || { stacked: false },
		lineOptions: payload.lineOptions || payload.line_options || {},
		axisOptions: payload.axisOptions || payload.axis_options || {},
	};

	// Let frappe.Chart handle supported types (bar, line, axis-mixed, pie, percentage, heatmap)
	$chart[0].chart = new frappe.Chart($chart[0], chartOptions);
}

function render_table($target, title, table_payload) {
	$target.empty();
	const $title = $(`<div class="fw-bold mb-1">${frappe.utils.escape_html(title)}</div>`);
	$target.append($title);

	if (!table_payload || !table_payload.rows || table_payload.rows.length === 0) {
		$target.append(`<div class="text-muted">${__("No data")}</div>`);
		return;
	}

	const columns = table_payload.columns || [];
	const rows = table_payload.rows || [];

	const $table = $('<table class="table table-sm mb-2"></table>');
	const $thead = $("<thead><tr></tr></thead>").appendTo($table);
	columns.forEach((col) => {
		$thead.find("tr").append(`<th>${frappe.utils.escape_html(col.label || col.fieldname || "")}</th>`);
	});
	const $tbody = $("<tbody></tbody>").appendTo($table);
	rows.forEach((row) => {
		const $tr = $("<tr class='cursor-pointer'></tr>");
		columns.forEach((col) => {
			const val = row[col.fieldname];
			const formatted = val === undefined ? "" : frappe.format(val, { fieldtype: col.fieldtype || "Data" });
			$tr.append(`<td>${formatted}</td>`);
		});
		$tbody.append($tr);
		if (table_payload.route_options) {
			$tr.on("click", () => apply_route(table_payload.route_options));
		}
	});
	const $table_wrap = $('<div class="table-responsive" style="max-height: 320px; overflow-y: auto;"></div>');
	$table_wrap.append($table);
	$target.append($table_wrap);

	const $view_all = $('<a class="small" href="#">' + __("View all") + "</a>");
	if (table_payload.route_options) {
		$view_all.on("click", (e) => {
			e.preventDefault();
			apply_route(table_payload.route_options);
		});
	} else {
		$view_all.addClass("disabled");
	}
	$target.append($view_all);
}
