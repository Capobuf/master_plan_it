import { Link, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import {
    Button,
    SelectInput,
    Textarea,
    TextInput,
    PageHeader,
    cx,
} from "../../../components/ui";
import AppLayout from "../../../layouts/AppLayout";
import type {
    ExpenseFormData,
    ExpenseFormPageProps,
    ExpenseFormRow,
    ExpenseRowRecord,
    ExpenseRowType,
} from "../../../types";

let localRowSequence = 0;
const nextLocalKey = () => `expense-row-${Date.now()}-${localRowSequence++}`;

export default function ExpenseForm({
    expense,
    planningYears = [],
    costCenters = [],
    vendors = [],
    plafonds = [],
    contracts = [],
    defaults,
    method,
}: ExpenseFormPageProps & { method: "post" | "put" }) {
    const hasGeneratedProvenance =
        expense?.rows.some((row) => Boolean(row.sourceKey)) ?? false;
    const form = useForm<ExpenseFormData>({
        planning_year_id: expense?.planningYearId ?? "",
        cost_center_id: expense?.costCenterId ?? "",
        kind: expense?.kind ?? "ordinary",
        title: expense?.title ?? "",
        notes: expense?.notes ?? "",
        contract_id: expense?.contractId ?? "",
        lock_version: expense?.lockVersion ?? null,
        rows:
            expense?.rows.map((row) => rowToForm(row, defaults.vatRate)) ??
            [newRow("estimate", defaults.vatRate)],
    });

    const updateRow = (index: number, patch: Partial<ExpenseFormRow>) => {
        form.setData(
            "rows",
            form.data.rows.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...patch } : row,
            ),
        );
    };

    const removeRow = (index: number) => {
        form.setData(
            "rows",
            form.data.rows
                .filter((_, rowIndex) => rowIndex !== index)
                .map((row, rowIndex) => ({ ...row, position: rowIndex + 1 })),
        );
    };

    const addRow = (type: ExpenseRowType) => {
        form.setData("rows", [
            ...form.data.rows,
            {
                ...newRow(type, defaults.vatRate),
                position: form.data.rows.length + 1,
            },
        ]);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (form.processing) return;
        if (method === "post") {
            form.post("/operational/expenses", { preserveScroll: true });
            return;
        }
        form.put(`/operational/expenses/${expense?.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <PageHeader
                title={method === "post" ? "New expense" : "Edit expense"}
                description="The server calculates all authoritative monetary values when you save."
                crumbs={[
                    { label: "Expenses", href: "/operational/expenses" },
                    { label: method === "post" ? "Create" : "Edit" },
                ]}
            />

            <form onSubmit={submit} className="space-y-5">
                {form.errors.lock_version ? (
                    <div
                        role="alert"
                        className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                    >
                        {form.errors.lock_version} Reload the expense before saving again.
                    </div>
                ) : null}
                <section className="mp-card">
                    <div className="mp-card-body">
                        <div className="mb-5">
                            <h2 className="text-lg font-semibold text-slate-900">
                                Expense header
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Select the planning context and identify this expense.
                            </p>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <SelectInput
                                label="Planning year"
                                required
                                disabled={hasGeneratedProvenance}
                                value={form.data.planning_year_id}
                                error={form.errors.planning_year_id}
                                onChange={(event) =>
                                    form.setData(
                                        "planning_year_id",
                                        numberOrEmpty(event.target.value),
                                    )
                                }
                            >
                                <option value="">Select a planning year</option>
                                {planningYears.map((year) => (
                                    <option key={year.value} value={year.value}>
                                        {year.label}
                                        {year.active ? " · Active" : ""}
                                    </option>
                                ))}
                            </SelectInput>
                            <SelectInput
                                label="Cost center"
                                required
                                value={form.data.cost_center_id}
                                error={form.errors.cost_center_id}
                                onChange={(event) =>
                                    form.setData(
                                        "cost_center_id",
                                        numberOrEmpty(event.target.value),
                                    )
                                }
                            >
                                <option value="">Select a cost center</option>
                                {costCenters.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </SelectInput>
                            <SelectInput
                                label="Kind"
                                value={form.data.kind}
                                error={form.errors.kind}
                                onChange={(event) =>
                                    form.setData(
                                        "kind",
                                        event.target.value as ExpenseFormData["kind"],
                                    )
                                }
                            >
                                <option value="ordinary">Ordinary</option>
                                <option value="plafond">Plafond</option>
                            </SelectInput>
                            <TextInput
                                label="Title"
                                required
                                value={form.data.title}
                                error={form.errors.title}
                                onChange={(event) =>
                                    form.setData("title", event.target.value)
                                }
                            />
                            <SelectInput
                                label="Contract (optional)"
                                disabled={hasGeneratedProvenance}
                                value={form.data.contract_id}
                                error={form.errors.contract_id}
                                onChange={(event) =>
                                    form.setData(
                                        "contract_id",
                                        numberOrEmpty(event.target.value),
                                    )
                                }
                            >
                                <option value="">No contract</option>
                                {contracts.map((contractOption) => (
                                    <option
                                        key={contractOption.value}
                                        value={contractOption.value}
                                    >
                                        {contractOption.label}
                                    </option>
                                ))}
                            </SelectInput>
                            <div className="sm:col-span-2">
                                <Textarea
                                    label="Notes"
                                    value={form.data.notes}
                                    error={form.errors.notes}
                                    onChange={(event) =>
                                        form.setData("notes", event.target.value)
                                    }
                                />
                            </div>
                            {expense?.contractId ? (
                                <div className="sm:col-span-2 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                                    This expense is linked to contract {expense.contractTitle ?? `#${expense.contractId}`}.
                                </div>
                            ) : null}
                            {hasGeneratedProvenance ? (
                                <div className="sm:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                    Contract source provenance and planning year are fixed for generated rows. Economic fields remain editable.
                                </div>
                            ) : null}
                        </div>
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Expense rows
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Estimates, quotes and Actuals are independent and may coexist.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="secondary" onClick={() => addRow("estimate")}>
                                Add Estimate
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => addRow("quote")}>
                                Add Quote
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => addRow("actual")}>
                                Add Actual
                            </Button>
                        </div>
                    </div>
                    {form.errors.rows ? (
                        <p role="alert" className="mb-3 text-sm text-red-600">
                            {form.errors.rows}
                        </p>
                    ) : null}
                    <div className="grid gap-4">
                        {form.data.rows.map((row, index) => (
                            <ExpenseRowEditor
                                key={row.local_key}
                                row={row}
                                index={index}
                                kind={form.data.kind}
                                vendors={vendors}
                                plafonds={plafonds}
                                errors={form.errors as Record<string, string>}
                                onChange={(patch) => updateRow(index, patch)}
                                onRemove={() => removeRow(index)}
                            />
                        ))}
                    </div>
                </section>

                <section className="mp-card p-5">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-semibold text-slate-900">Current totals</h2>
                            {expense ? (
                                <dl className="mt-3 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                                    <Total label="Net" value={expense.net} />
                                    <Total label="VAT" value={expense.vat} />
                                    <Total label="Gross" value={expense.gross} />
                                </dl>
                            ) : (
                                <p className="mt-1 text-sm text-slate-500">
                                    Totals will be calculated by the server when the expense is saved.
                                </p>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-3">
                            <Link
                                href={
                                    expense
                                        ? `/operational/expenses/${expense.id}`
                                        : "/operational/expenses"
                                }
                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3.5 text-sm text-gray-700 shadow-theme-xs ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? "Saving…"
                                    : method === "post"
                                      ? "Create expense"
                                      : "Save changes"}
                            </Button>
                        </div>
                    </div>
                </section>
            </form>
        </AppLayout>
    );
}

function ExpenseRowEditor({
    row,
    index,
    kind,
    vendors,
    plafonds,
    errors,
    onChange,
    onRemove,
}: {
    row: ExpenseFormRow;
    index: number;
    kind: ExpenseFormData["kind"];
    vendors: ExpenseFormPageProps["vendors"];
    plafonds: ExpenseFormPageProps["plafonds"];
    errors: Record<string, string>;
    onChange: (patch: Partial<ExpenseFormRow>) => void;
    onRemove: () => void;
}) {
    const fieldError = (field: keyof ExpenseFormRow) =>
        errors[`rows.${index}.${String(field)}`];
    const dateMode = row.spend_date ? "single" : "period";

    return (
        <article className="mp-card">
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Row {index + 1}
                    </span>
                    <h3 className="font-semibold capitalize text-slate-900">{row.type}</h3>
                </div>
                <Button type="button" variant="danger" onClick={onRemove}>
                    Delete row
                </Button>
            </div>
            <div className="mp-card-body grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                {fieldError("lock_version") ? (
                    <p
                        role="alert"
                        className="md:col-span-2 xl:col-span-3 text-sm text-amber-700"
                    >
                        {fieldError("lock_version")} Reload this row before saving again.
                    </p>
                ) : null}
                <SelectInput
                    label="Type"
                    value={row.type}
                    error={fieldError("type")}
                    onChange={(event) =>
                        onChange({ type: event.target.value as ExpenseRowType })
                    }
                >
                    <option value="estimate">Estimate</option>
                    <option value="quote">Quote</option>
                    <option value="actual">Actual</option>
                </SelectInput>
                <SelectInput
                    label={`Vendor${kind === "ordinary" ? "" : " (optional)"}`}
                    required={kind === "ordinary"}
                    value={row.vendor_id}
                    error={fieldError("vendor_id")}
                    onChange={(event) =>
                        onChange({ vendor_id: numberOrEmpty(event.target.value) })
                    }
                >
                    <option value="">Select a vendor</option>
                    {vendors.map((vendor) => (
                        <option key={vendor.value} value={vendor.value}>
                            {vendor.label}
                        </option>
                    ))}
                </SelectInput>
                <TextInput
                    label="Description"
                    required
                    value={row.description}
                    error={fieldError("description")}
                    onChange={(event) => onChange({ description: event.target.value })}
                />
                <TextInput
                    label="Quantity"
                    inputMode="decimal"
                    placeholder="1.000000"
                    value={row.quantity}
                    error={fieldError("quantity")}
                    onChange={(event) => onChange({ quantity: event.target.value })}
                />
                <TextInput
                    label="Unit price"
                    inputMode="decimal"
                    placeholder="0.000000"
                    value={row.unit_price}
                    error={fieldError("unit_price")}
                    onChange={(event) => onChange({ unit_price: event.target.value })}
                />
                <TextInput
                    label="Entered amount"
                    inputMode="decimal"
                    required
                    value={row.entered_amount}
                    error={fieldError("entered_amount")}
                    onChange={(event) => onChange({ entered_amount: event.target.value })}
                />
                <TextInput
                    label="VAT rate"
                    inputMode="decimal"
                    value={row.vat_rate}
                    error={fieldError("vat_rate")}
                    onChange={(event) => onChange({ vat_rate: event.target.value })}
                />
                <label className="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={row.amount_includes_vat}
                        onChange={(event) =>
                            onChange({ amount_includes_vat: event.target.checked })
                        }
                    />
                    Entered amount includes VAT
                </label>
                <label className="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={row.is_extra}
                        onChange={(event) =>
                            onChange({
                                is_extra: event.target.checked,
                                funded_plafond_expense_id: event.target.checked
                                    ? ""
                                    : row.funded_plafond_expense_id,
                            })
                        }
                    />
                    Extra
                </label>
                {kind === "ordinary" ? (
                    <SelectInput
                        label="Funded Plafond"
                        disabled={row.is_extra}
                        value={row.funded_plafond_expense_id}
                        error={fieldError("funded_plafond_expense_id")}
                        onChange={(event) =>
                            onChange({
                                funded_plafond_expense_id: numberOrEmpty(
                                    event.target.value,
                                ),
                                is_extra: event.target.value ? false : row.is_extra,
                            })
                        }
                    >
                        <option value="">No Plafond funding</option>
                        {plafonds.map((plafond) => (
                            <option key={plafond.value} value={plafond.value}>
                                {plafond.label}
                            </option>
                        ))}
                    </SelectInput>
                ) : null}
                <TextInput
                    label="External reference"
                    value={row.external_reference}
                    error={fieldError("external_reference")}
                    onChange={(event) =>
                        onChange({ external_reference: event.target.value })
                    }
                />

                <fieldset className="md:col-span-2 xl:col-span-3">
                            <legend className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Date model</legend>
                    <div className="mb-4 flex flex-wrap gap-4">
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="radio"
                                checked={dateMode === "single"}
                                onChange={() =>
                                    onChange({
                                        spend_date:
                                            row.spend_date ||
                                            new Date().toISOString().slice(0, 10),
                                        period_start: "",
                                        period_end: "",
                                        distribution: "",
                                    })
                                }
                            />
                            Single spend date
                        </label>
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="radio"
                                checked={dateMode === "period"}
                                onChange={() =>
                                    onChange({
                                        spend_date: "",
                                        distribution: row.distribution || "all",
                                    })
                                }
                            />
                            Period
                        </label>
                    </div>
                    {dateMode === "single" ? (
                        <div className="max-w-sm">
                            <TextInput
                                label="Spend date"
                                type="date"
                                required
                                value={row.spend_date}
                                error={fieldError("spend_date")}
                                onChange={(event) =>
                                    onChange({ spend_date: event.target.value })
                                }
                            />
                        </div>
                    ) : (
                        <div className="grid gap-5 sm:grid-cols-3">
                            <TextInput
                                label="Period start"
                                type="date"
                                required
                                value={row.period_start}
                                error={fieldError("period_start")}
                                onChange={(event) =>
                                    onChange({ period_start: event.target.value })
                                }
                            />
                            <TextInput
                                label="Period end"
                                type="date"
                                required
                                value={row.period_end}
                                error={fieldError("period_end")}
                                onChange={(event) =>
                                    onChange({ period_end: event.target.value })
                                }
                            />
                            <SelectInput
                                label="Distribution"
                                required
                                value={row.distribution}
                                error={fieldError("distribution")}
                                onChange={(event) =>
                                    onChange({
                                        distribution: event.target
                                            .value as ExpenseFormRow["distribution"],
                                    })
                                }
                            >
                                <option value="all">Across the period</option>
                                <option value="start">At period start</option>
                                <option value="end">At period end</option>
                            </SelectInput>
                        </div>
                    )}
                </fieldset>
                {row.type !== "actual" ? (
                    <p className="md:col-span-2 xl:col-span-3 text-xs text-slate-500">
                        Estimate and Quote amounts must be zero or positive. Actuals may also record negative adjustments.
                    </p>
                ) : null}
            </div>
        </article>
    );
}

function newRow(type: ExpenseRowType, vatRate: string): ExpenseFormRow {
    return {
        local_key: nextLocalKey(),
        id: null,
        position: 1,
        vendor_id: "",
        type,
        description: "",
        quantity: "1.000000",
        unit_price: "0.000000",
        entered_amount: "0.000000",
        amount_includes_vat: false,
        vat_rate: vatRate,
        is_extra: false,
        funded_plafond_expense_id: "",
        spend_date: new Date().toISOString().slice(0, 10),
        period_start: "",
        period_end: "",
        distribution: "",
        external_reference: "",
        lock_version: null,
    };
}

function rowToForm(row: ExpenseRowRecord, vatRate: string): ExpenseFormRow {
    return {
        local_key: row.localKey ?? `expense-row-${row.id}`,
        id: row.id,
        position: row.position,
        vendor_id: row.vendorId ?? "",
        type: row.type,
        description: row.description,
        quantity: row.quantity ?? "1.000000",
        unit_price: row.unitPrice ?? "0.000000",
        entered_amount: row.enteredAmount ?? "0.000000",
        amount_includes_vat: row.amountIncludesVat ?? false,
        vat_rate: row.vatRate ?? vatRate,
        is_extra: row.isExtra ?? false,
        funded_plafond_expense_id: row.fundedPlafondExpenseId ?? "",
        spend_date: dateInputValue(row.spendDate),
        period_start: dateInputValue(row.periodStart),
        period_end: dateInputValue(row.periodEnd),
        distribution: row.distribution ?? (row.spendDate ? "" : "all"),
        external_reference: row.externalReference ?? "",
        lock_version: row.lockVersion ?? null,
    };
}

function numberOrEmpty(value: string): number | "" {
    return value === "" ? "" : Number(value);
}

function dateInputValue(value?: string | null): string {
    return value ? value.slice(0, 10) : "";
}

function Total({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-slate-500">{label}</dt>
            <dd className={cx("font-semibold text-slate-900")}>{value}</dd>
        </div>
    );
}
