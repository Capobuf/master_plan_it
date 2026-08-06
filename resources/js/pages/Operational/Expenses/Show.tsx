import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import {
    Badge,
    Button,
    ConfirmModal,
    EmptyState,
    PageHeader,
    SelectInput,
} from "../../../components/ui";
import AppLayout from "../../../layouts/AppLayout";
import type { ExpenseRowRecord, ExpenseShowPageProps } from "../../../types";

export default function ExpenseShow({
    expense,
    abilities,
    navigation,
}: ExpenseShowPageProps) {
    const [deleting, setDeleting] = useState(false);
    const [confirmingRow, setConfirmingRow] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);
    const [allowRegeneration, setAllowRegeneration] = useState<"yes" | "no" | "">("");
    const isGenerated = expense.rows.some((row) => Boolean(row.sourceKey));
    const canUpdate = abilities?.update ?? navigation.canUpdateExpenses;

    const destroy = () => {
        if (isGenerated && allowRegeneration === "") return;
        setProcessing(true);
        router.delete(`/operational/expenses/${expense.id}`, {
            data: {
                lock_version: expense.lockVersion ?? 0,
                allow_regeneration: isGenerated
                    ? allowRegeneration === "yes"
                    : undefined,
            },
            onSuccess: () => setDeleting(false),
            onFinish: () => setProcessing(false),
        });
    };

    const confirmActual = () => {
        if (confirmingRow === null) return;
        const row = expense.rows.find((candidate) => candidate.id === confirmingRow);
        setProcessing(true);
        router.post(
            `/operational/expenses/${expense.id}/rows/${confirmingRow}/confirm`,
            { lock_version: row?.lockVersion ?? 0 },
            {
                preserveScroll: true,
                onSuccess: () => setConfirmingRow(null),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={expense.title} />
            <PageHeader
                title={expense.title}
                description="Expense detail and current monetary results."
                crumbs={[
                    { label: "Expenses", href: "/operational/expenses" },
                    { label: expense.title },
                ]}
                action={
                    <div className="flex flex-wrap gap-2">
                        {canUpdate ? (
                            <Link
                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3.5 text-sm text-gray-700 shadow-theme-xs ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50"
                                href={`/operational/expenses/${expense.id}/edit`}
                            >
                                Edit
                            </Link>
                        ) : null}
                        {abilities?.delete ? (
                            <Button
                                variant="danger"
                                onClick={() => {
                                    setAllowRegeneration("");
                                    setDeleting(true);
                                }}
                            >
                                Delete
                            </Button>
                        ) : null}
                    </div>
                }
            />

            <div className="grid gap-5 lg:grid-cols-3">
                <section className="mp-card lg:col-span-2">
                    <div className="mp-card-body">
                        <dl className="grid gap-5 sm:grid-cols-2">
                            <Detail
                                label="Planning year"
                                value={expense.planningYearLabel}
                            />
                            <Detail label="Cost center" value={expense.costCenterName} />
                            <Detail label="Kind" value={expense.kind} />
                            {expense.contractId ? (
                                <div>
                                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Contract
                                    </dt>
                                    <dd className="mt-1 text-sm font-medium">
                                        {expense.contractIsCurrent ? (
                                            <Link
                                                href={
                                                    expense.contractHref ??
                                                    `/operational/contracts/${expense.contractId}`
                                                }
                                                className="text-brand-700 hover:text-brand-600"
                                            >
                                                {expense.contractTitle ??
                                                    `Contract #${expense.contractId}`}
                                            </Link>
                                        ) : (
                                            <span className="text-slate-900">
                                                {expense.contractTitle ??
                                                    `Contract #${expense.contractId}`}
                                                <span className="ml-2 text-xs font-normal text-slate-500">
                                                    Preserved source
                                                </span>
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            ) : null}
                            {expense.notes ? (
                                <div className="sm:col-span-2">
                                    <Detail label="Notes" value={expense.notes} />
                                </div>
                            ) : null}
                        </dl>
                    </div>
                </section>
                <section className="mp-card">
                    <div className="mp-card-body space-y-4">
                        <Detail label="Net" value={expense.net} />
                        <Detail label="VAT" value={expense.vat} />
                        <Detail label="Gross" value={expense.gross} />
                    </div>
                </section>
            </div>

            <section className="mt-5">
                <h2 className="mb-3 text-lg font-semibold text-slate-900">
                    Expense rows
                </h2>
                {expense.rows.length > 0 ? (
                    <div className="grid gap-4">
                        {expense.rows.map((row) => (
                            <ExpenseRowCard
                                key={row.id}
                                row={row}
                                canConfirm={
                                    (abilities?.confirmActual ?? row.canConfirm) === true
                                }
                                onConfirm={() => setConfirmingRow(row.id)}
                            />
                        ))}
                    </div>
                ) : (
                    <EmptyState title="No current rows">
                        This expense has no current rows.
                    </EmptyState>
                )}
            </section>

            <ConfirmModal
                open={deleting}
                title="Delete expense"
                confirmLabel="Delete expense"
                busy={processing || (isGenerated && allowRegeneration === "")}
                onClose={() => setDeleting(false)}
                onConfirm={destroy}
            >
                <p>
                    This permanently removes the expense from current registers and
                    economic totals. Revision and audit evidence are retained.
                </p>
                {isGenerated ? (
                    <div className="mt-4">
                        <SelectInput
                            label="Allow this contract occurrence to be generated again?"
                            value={allowRegeneration}
                            onChange={(event) =>
                                setAllowRegeneration(
                                    event.target.value as "yes" | "no" | "",
                                )
                            }
                        >
                            <option value="">Choose an option</option>
                            <option value="yes">Yes, allow regeneration</option>
                            <option value="no">No, suppress this occurrence</option>
                        </SelectInput>
                    </div>
                ) : null}
            </ConfirmModal>

            <ConfirmModal
                open={confirmingRow !== null}
                title="Confirm Actual"
                confirmLabel="Confirm Actual"
                busy={processing}
                onClose={() => setConfirmingRow(null)}
                onConfirm={confirmActual}
            >
                Confirm this Actual value? It remains editable, but future contract
                synchronization will no longer overwrite it.
            </ConfirmModal>
        </AppLayout>
    );
}

function ExpenseRowCard({
    row,
    canConfirm,
    onConfirm,
}: {
    row: ExpenseRowRecord;
    canConfirm: boolean;
    onConfirm: () => void;
}) {
    const isConfirmable =
        row.type === "actual" && row.confirmationState === "to_confirm";

    return (
        <article className="mp-card p-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Row {row.position}
                        </span>
                        <Badge tone={row.type === "actual" ? "blue" : "slate"}>
                            {row.type}
                        </Badge>
                        {row.confirmationState ? (
                            <Badge
                                tone={
                                    row.confirmationState === "confirmed"
                                        ? "green"
                                        : "amber"
                                }
                            >
                                {row.confirmationState === "confirmed"
                                    ? "Confirmed"
                                    : "To confirm"}
                            </Badge>
                        ) : null}
                        {row.isSystemManaged ? (
                            <Badge tone="blue">Contract managed</Badge>
                        ) : null}
                    </div>
                    <h3 className="mt-2 font-semibold text-slate-900">
                        {row.description}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {row.vendorName ?? "No vendor"}
                        {row.externalReference
                            ? ` · Ref. ${row.externalReference}`
                            : ""}
                    </p>
                </div>
                {isConfirmable && canConfirm ? (
                    <Button variant="secondary" onClick={onConfirm}>
                        Confirm Actual
                    </Button>
                ) : null}
            </div>
            <dl className="mt-5 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-3 lg:grid-cols-6">
                <Detail label="Net" value={row.net} />
                <Detail label="VAT" value={row.vat} />
                <Detail label="Gross" value={row.gross} />
                <Detail
                    label="Date"
                    value={
                        row.spendDate ??
                        (row.periodStart && row.periodEnd
                            ? `${row.periodStart} – ${row.periodEnd}`
                            : null)
                    }
                />
                <Detail label="Distribution" value={row.distribution} />
                <Detail
                    label="Funding"
                    value={
                        row.isExtra
                            ? "Extra"
                            : (row.fundedPlafondTitle ?? "Standard")
                    }
                />
            </dl>
        </article>
    );
}

function Detail({ label, value }: { label: string; value: unknown }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium capitalize text-slate-900">
                {value === null || value === undefined || value === "" ? "—" : String(value)}
            </dd>
        </div>
    );
}
