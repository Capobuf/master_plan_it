import { Head, Link, router } from "@inertiajs/react";
import { useState, type ReactNode } from "react";
import EconomicChart from "../../components/EconomicChart";
import {
    Badge,
    EmptyState,
    ErrorState,
    LoadingState,
    PageHeader,
    SelectInput,
} from "../../components/ui";
import AppLayout from "../../layouts/AppLayout";
import type {
    DashboardPageProps,
    DashboardTableRow,
    EconomicSummaryProps,
} from "../../types";

export default function Dashboard({
    yearOptions = [],
    selectedYear,
    summary,
    hasEconomicData,
    charts = {},
    topCostCenters = [],
    recentExpenses = [],
    generatedExpensesToConfirm = [],
    activeContracts = [],
    upcomingContractEvents = [],
    generatedAt,
    stale = false,
    error,
    navigation,
    tenant,
}: DashboardPageProps) {
    const [loading, setLoading] = useState(false);
    const containsEconomicRows = hasEconomicData ?? summary != null;

    if (error) {
        return (
            <AppLayout>
                <Head title="Dashboard" />
                <PageHeader title="Dashboard" crumbs={[{ label: "Dashboard" }]} />
                <ErrorState message={error} />
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <PageHeader
                title="Economic dashboard"
                description={
                    tenant.current
                        ? `Current economic position for ${tenant.current.name}.`
                        : "Select a tenant to view its economic position."
                }
                crumbs={[{ label: "Dashboard" }]}
                action={
                    yearOptions.length > 0 ? (
                        <div className="w-full sm:w-56">
                            <SelectInput
                                label="Planning year"
                                value={selectedYear ?? ""}
                                onChange={(event) =>
                                    router.get(
                                        "/operational",
                                        { year: event.target.value },
                                        {
                                            preserveState: true,
                                            replace: true,
                                            onStart: () => setLoading(true),
                                            onFinish: () => setLoading(false),
                                        },
                                    )
                                }
                            >
                                {yearOptions.map((year) => (
                                    <option key={year.value} value={year.value}>
                                        {year.label}
                                        {year.active ? " · Active" : ""}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                    ) : undefined
                }
            />

            {stale ? (
                <div
                    role="status"
                    className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                >
                    This view may be stale. Refresh to load the latest current records.
                    {generatedAt ? ` Last generated ${generatedAt}.` : ""}
                </div>
            ) : null}

            {loading ? (
                <div aria-live="polite" aria-busy="true">
                    <LoadingState />
                </div>
            ) : yearOptions.length === 0 ? (
                <DashboardEmpty
                    canCreateExpense={navigation.canCreateExpenses}
                    canCreateContract={navigation.canCreateContracts}
                />
            ) : (
                <>
                    <KpiGrid summary={summary} />
                    {!containsEconomicRows ? (
                        <EconomicEmptyGuide
                            canCreateExpense={navigation.canCreateExpenses}
                            canCreateContract={navigation.canCreateContracts}
                        />
                    ) : null}

                    <section className="mt-6 grid gap-5 xl:grid-cols-3">
                        <ChartCard title="Monthly current position" className="xl:col-span-2">
                            <EconomicChart
                                data={charts.monthly}
                                type="line"
                                label="Monthly current position"
                            />
                        </ChartCard>
                        <ChartCard title="Breakdown by type">
                            <EconomicChart
                                data={charts.byType}
                                type="doughnut"
                                label="Economic breakdown by expense type"
                            />
                        </ChartCard>
                        <ChartCard title="Cost center distribution" className="xl:col-span-3">
                            <EconomicChart
                                data={charts.byCostCenter}
                                type="bar"
                                label="Economic distribution by cost center"
                            />
                        </ChartCard>
                    </section>

                    <section className="mt-6 grid gap-5 xl:grid-cols-2">
                        <DataCard title="Top cost centers" rows={topCostCenters} />
                        <DataCard title="Recent expenses" rows={recentExpenses} />
                        <DataCard
                            title="Generated Actuals to confirm"
                            rows={generatedExpensesToConfirm}
                            empty="No generated Actuals need confirmation."
                        />
                        <DataCard
                            title="Active contracts"
                            rows={activeContracts}
                            empty="No active contracts."
                        />
                        <div className="xl:col-span-2">
                            <DataCard
                                title="Upcoming contract ends and renewals"
                                rows={upcomingContractEvents}
                                empty="No upcoming contract events."
                            />
                        </div>
                    </section>
                </>
            )}
        </AppLayout>
    );
}

function KpiGrid({ summary }: { summary?: EconomicSummaryProps | null }) {
    const values = summary ?? zeroSummary;
    const primary = [
        {
            label: "Official current position",
            value: values.officialCurrentPosition,
            description: `${values.officialBasis.toUpperCase()} basis`,
        },
        { label: "Net", value: values.net },
        { label: "VAT", value: values.vat },
        { label: "Gross", value: values.gross },
    ];
    const components = [
        { label: "Estimate", value: values.estimate },
        { label: "Quote", value: values.quote },
        { label: "Actual", value: values.actual },
        { label: "Actual to confirm", value: values.actualToConfirm },
        { label: "Actual confirmed", value: values.actualConfirmed },
    ];
    const plafond = [
        { label: "Plafond allocated", value: values.plafondAllocated },
        { label: "Plafond consumed", value: values.plafondConsumed },
        { label: "Plafond residual", value: values.plafondResidual },
        { label: "Plafond overrun", value: values.plafondOverrun },
    ];

    return (
        <section aria-labelledby="economic-kpis">
            <div className="mb-3 flex items-center justify-between">
                <h2 id="economic-kpis" className="text-lg font-semibold text-slate-900">
                    Economic position
                </h2>
                {!summary ? <Badge tone="slate">No economic rows</Badge> : null}
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {primary.map((item, index) => (
                    <KpiCard key={item.label} {...item} prominent={index === 0} />
                ))}
            </div>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {components.map((item) => (
                    <KpiCard key={item.label} {...item} />
                ))}
            </div>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {plafond.map((item) => (
                    <KpiCard key={item.label} {...item} />
                ))}
            </div>
        </section>
    );
}

function KpiCard({
    label,
    value,
    description,
    prominent = false,
}: {
    label: string;
    value: string;
    description?: string;
    prominent?: boolean;
}) {
    return (
        <article className={`mp-card p-5 ${prominent ? "border-brand-300 bg-brand-50" : ""}`}>
            <p className="text-sm font-medium text-slate-500">{label}</p>
            <p className="mt-2 text-xl font-bold text-slate-900">{value}</p>
            {description ? (
                <p className="mt-1 text-xs font-semibold text-brand-700">{description}</p>
            ) : null}
        </article>
    );
}

function ChartCard({
    title,
    className = "",
    children,
}: {
    title: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <article className={`mp-card p-5 ${className}`}>
            <h2 className="mb-4 font-semibold text-slate-900">{title}</h2>
            {children}
        </article>
    );
}

function DataCard({
    title,
    rows,
    empty = "No data for this planning year.",
}: {
    title: string;
    rows: DashboardTableRow[];
    empty?: string;
}) {
    return (
        <article className="mp-card overflow-hidden">
            <div className="border-b border-slate-200 px-5 py-4">
                <h2 className="font-semibold text-slate-900">{title}</h2>
            </div>
            {rows.length > 0 ? (
                <ul className="divide-y divide-slate-100">
                    {rows.map((row, index) => (
                        <li
                            key={row.id ?? `${row.label}-${index}`}
                            className="flex items-start justify-between gap-4 px-5 py-4"
                        >
                            <div className="min-w-0">
                                {row.href ? (
                                    <Link
                                        href={row.href}
                                        className="font-semibold text-slate-900 hover:text-brand-700"
                                    >
                                        {row.label}
                                    </Link>
                                ) : (
                                    <p className="font-semibold text-slate-900">
                                        {row.label}
                                    </p>
                                )}
                                {row.secondary ? (
                                    <p className="mt-1 truncate text-sm text-slate-500">
                                        {row.secondary}
                                    </p>
                                ) : null}
                            </div>
                            <div className="shrink-0 text-right">
                                {row.value ? (
                                    <p className="font-semibold text-slate-900">
                                        {row.value}
                                    </p>
                                ) : null}
                                {row.state ? (
                                    <Badge tone={stateTone(row.state)}>{row.state}</Badge>
                                ) : null}
                                {row.date ? (
                                    <p className="mt-1 text-xs text-slate-500">{row.date}</p>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="p-6 text-sm text-slate-500">{empty}</p>
            )}
        </article>
    );
}

function DashboardEmpty({
    canCreateExpense,
    canCreateContract,
}: {
    canCreateExpense: boolean;
    canCreateContract: boolean;
}) {
    return (
        <EmptyState title="No planning years available">
            <p>Create a planning year before recording economic activity.</p>
            <div className="mt-4 flex flex-wrap justify-center gap-3">
                {canCreateExpense ? (
                    <Link
                        href="/operational/expenses/create"
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3.5 text-sm text-white shadow-theme-xs transition hover:bg-brand-600"
                    >
                        Create expense
                    </Link>
                ) : null}
                {canCreateContract ? (
                    <Link
                        href="/operational/contracts/create"
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3.5 text-sm text-gray-700 shadow-theme-xs ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50"
                    >
                        Create contract
                    </Link>
                ) : null}
            </div>
        </EmptyState>
    );
}

function EconomicEmptyGuide({
    canCreateExpense,
    canCreateContract,
}: {
    canCreateExpense: boolean;
    canCreateContract: boolean;
}) {
    return (
        <div className="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
            <p className="font-semibold">No economic rows in this planning year</p>
            <p className="mt-1 text-blue-800">
                The zero values above are valid. Create an Expense directly, or create a Contract and materialize its occurrences.
            </p>
            <div className="mt-4 flex flex-wrap gap-3">
                {canCreateExpense ? (
                    <Link
                        href="/operational/expenses/create"
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3.5 text-sm text-white shadow-theme-xs transition hover:bg-brand-600"
                    >
                        Create expense
                    </Link>
                ) : null}
                {canCreateContract ? (
                    <Link
                        href="/operational/contracts/create"
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3.5 text-sm text-gray-700 shadow-theme-xs ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50"
                    >
                        Create contract
                    </Link>
                ) : null}
            </div>
        </div>
    );
}

function stateTone(state: string): "slate" | "green" | "red" | "amber" | "blue" {
    const normalized = state.toLowerCase();
    if (normalized.includes("confirm") && !normalized.includes("to")) return "green";
    if (normalized.includes("to confirm") || normalized.includes("soon")) return "amber";
    if (normalized.includes("active")) return "green";
    if (normalized.includes("error") || normalized.includes("overrun")) return "red";
    return "slate";
}

const zeroSummary: EconomicSummaryProps = {
    officialBasis: "net",
    officialCurrentPosition: "0",
    net: "0",
    vat: "0",
    gross: "0",
    estimate: "0",
    quote: "0",
    actual: "0",
    actualToConfirm: "0",
    actualConfirmed: "0",
    plafondAllocated: "0",
    plafondConsumed: "0",
    plafondResidual: "0",
    plafondOverrun: "0",
};
