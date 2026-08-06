import { Head, Link, router } from "@inertiajs/react";
import { EmptyState, PageHeader, Pagination, SelectInput } from "../../../components/ui";
import AppLayout from "../../../layouts/AppLayout";
import type { ExpenseIndexPageProps } from "../../../types";

export default function ExpensesIndex({
    expenses,
    yearOptions = [],
    selectedYear,
    totals,
    abilities,
    navigation,
}: ExpenseIndexPageProps) {
    const rows = expenses?.data ?? [];
    const canCreate = abilities?.create ?? navigation.canCreateExpenses;

    return (
        <AppLayout>
            <Head title="Expenses" />
            <PageHeader
                title="Expense register"
                description="Current expenses for the selected planning year."
                crumbs={[{ label: "Expenses" }]}
                action={
                    canCreate ? (
                        <Link
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3.5 text-sm text-white shadow-theme-xs transition hover:bg-brand-600"
                            href="/operational/expenses/create"
                        >
                            New expense
                        </Link>
                    ) : undefined
                }
            />

            <div className="mb-5 max-w-xs">
                <SelectInput
                    label="Planning year"
                    value={selectedYear ?? ""}
                    onChange={(event) =>
                        router.get(
                            "/operational/expenses",
                            { year: event.target.value || undefined },
                            { preserveState: true, replace: true },
                        )
                    }
                >
                    <option value="">All years</option>
                    {yearOptions.map((year) => (
                        <option key={year.value} value={year.value}>
                            {year.label}
                            {year.active ? " · Active" : ""}
                        </option>
                    ))}
                </SelectInput>
            </div>

            {totals ? (
                <div className="mb-5 grid gap-4 sm:grid-cols-3">
                    {[
                        ["Net", totals.net],
                        ["VAT", totals.vat],
                        ["Gross", totals.gross],
                    ].map(([label, value]) => (
                        <div className="mp-card p-5" key={label}>
                            <div className="text-sm text-slate-500">{label}</div>
                            <div className="mt-2 text-xl font-bold text-slate-900">
                                {value}
                            </div>
                        </div>
                    ))}
                </div>
            ) : null}

            {rows.length > 0 ? (
                <>
                    <div className="mp-card operational-table-scroll overflow-x-auto">
                        <table className="mp-table">
                            <thead>
                                <tr>
                                    <th>Planning year</th>
                                    <th>Expense</th>
                                    <th>Cost center</th>
                                    <th>Rows</th>
                                    <th>Net</th>
                                    <th>VAT</th>
                                    <th>Gross</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((expense) => (
                                    <tr key={expense.id}>
                                        <td>{expense.planningYearLabel}</td>
                                        <td>
                                            <Link
                                                href={`/operational/expenses/${expense.id}`}
                                                className="font-semibold text-slate-900 hover:text-brand-700"
                                            >
                                                {expense.title}
                                            </Link>
                                            <div className="text-xs capitalize text-slate-500">
                                                {expense.kind}
                                            </div>
                                            {expense.contractTitle ? (
                                                expense.contractHref ? (
                                                    <Link
                                                        href={expense.contractHref}
                                                        className="mt-1 block text-xs font-medium text-brand-700 hover:text-brand-600"
                                                    >
                                                        {expense.contractTitle}
                                                    </Link>
                                                ) : (
                                                    <span className="mt-1 block text-xs text-slate-500">
                                                        {expense.contractTitle}
                                                    </span>
                                                )
                                            ) : null}
                                        </td>
                                        <td>{expense.costCenterName}</td>
                                        <td>{expense.rowCount}</td>
                                        <td>{expense.net}</td>
                                        <td>{expense.vat}</td>
                                        <td className="font-semibold text-slate-900">
                                            {expense.gross}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-5">
                        <Pagination links={expenses.links} />
                    </div>
                </>
            ) : (
                <EmptyState title="No expenses found">
                    <p>There are no expenses for the selected criteria.</p>
                    {canCreate ? (
                        <Link
                            href="/operational/expenses/create"
                            className="mt-4 inline-flex font-semibold text-brand-700"
                        >
                            Create the first expense →
                        </Link>
                    ) : null}
                </EmptyState>
            )}
        </AppLayout>
    );
}
