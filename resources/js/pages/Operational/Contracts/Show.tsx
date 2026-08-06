import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import {
    Badge,
    Button,
    ConfirmModal,
    EmptyState,
    PageHeader,
    SelectInput,
    Textarea,
} from "../../../components/ui";
import AppLayout from "../../../layouts/AppLayout";
import type {
    ContractShowPageProps,
    ContractTermRecord,
    SuppressedOccurrence,
} from "../../../types";

export default function ContractShow({
    contract,
    generatedExpenses: generatedExpenseProps = [],
    suppressedOccurrences: suppressedOccurrenceProps = [],
    revisionActivity: revisionActivityProps = [],
    abilities,
    planningYears = [],
    deletionReasonRequired = false,
}: ContractShowPageProps) {
    const [processing, setProcessing] = useState(false);
    const [deleteContract, setDeleteContract] = useState(false);
    const [deleteTerm, setDeleteTerm] = useState<ContractTermRecord | null>(null);
    const [deletionReason, setDeletionReason] = useState("");
    const [selectedYear, setSelectedYear] = useState<number | "">(
        planningYears[0]?.value ?? "",
    );
    const generatedExpenses =
        contract.generatedExpenses ?? generatedExpenseProps;
    const suppressedOccurrences =
        contract.suppressedOccurrences ?? suppressedOccurrenceProps;
    const revisionActivity = contract.revisionActivity ?? revisionActivityProps;

    const action = (
        url: string,
        data: Record<string, string | number | boolean | null | undefined> = {},
    ) => {
        if (processing) return;
        setProcessing(true);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const destroyContract = () => {
        if (deletionReasonRequired && !deletionReason.trim()) return;
        setProcessing(true);
        router.delete(`/operational/contracts/${contract.id}`, {
            data: {
                lock_version: contract.lockVersion,
                deletion_reason: deletionReason.trim() || null,
            },
            onSuccess: () => setDeleteContract(false),
            onFinish: () => setProcessing(false),
        });
    };

    const destroyTerm = () => {
        if (!deleteTerm || (deletionReasonRequired && !deletionReason.trim())) return;
        setProcessing(true);
        router.delete(
            `/operational/contracts/${contract.id}/terms/${deleteTerm.id}`,
            {
                data: {
                    lock_version: deleteTerm.lockVersion,
                    deletion_reason: deletionReason.trim() || null,
                },
                preserveScroll: true,
                onSuccess: () => setDeleteTerm(null),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={contract.title} />
            <PageHeader
                title={contract.title}
                description="Contract terms, generated expenses and occurrence controls."
                crumbs={[
                    { label: "Contracts", href: "/operational/contracts" },
                    { label: contract.title },
                ]}
                action={
                    <div className="flex flex-wrap gap-2">
                        {abilities?.update ? (
                            <Link
                                href={`/operational/contracts/${contract.id}/edit`}
                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3.5 text-sm text-gray-700 shadow-theme-xs ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50"
                            >
                                Edit
                            </Link>
                        ) : null}
                        {abilities?.delete ? (
                            <Button
                                variant="danger"
                                onClick={() => {
                                    setDeletionReason("");
                                    setDeleteContract(true);
                                }}
                            >
                                Delete contract
                            </Button>
                        ) : null}
                    </div>
                }
            />

            <div className="grid gap-5 lg:grid-cols-3">
                <section className="mp-card lg:col-span-2">
                    <div className="mp-card-body">
                        <div className="mb-5 flex items-center gap-3">
                            <Badge tone={contract.active ? "green" : "slate"}>
                                {contract.active ? "Active" : "Inactive"}
                            </Badge>
                        </div>
                        <dl className="grid gap-5 sm:grid-cols-2">
                            <Detail label="Vendor" value={contract.vendorName} />
                            <Detail label="Cost center" value={contract.costCenterName} />
                            <Detail
                                label="Date range"
                                value={dateRange(
                                    contract.effectiveStart,
                                    contract.effectiveEnd,
                                )}
                            />
                            <Detail
                                label="Renewal notice"
                                value={
                                    contract.renewalNoticeDays === null ||
                                    contract.renewalNoticeDays === undefined
                                        ? null
                                        : `${contract.renewalNoticeDays} days`
                                }
                            />
                            <Detail label="Renewal date" value={contract.renewalDate} />
                            {contract.description ? (
                                <div className="sm:col-span-2">
                                    <Detail
                                        label="Description"
                                        value={contract.description}
                                    />
                                </div>
                            ) : null}
                            {contract.renewalNotes ? (
                                <div className="sm:col-span-2">
                                    <Detail
                                        label="Renewal notes"
                                        value={contract.renewalNotes}
                                    />
                                </div>
                            ) : null}
                        </dl>
                    </div>
                </section>
                <section className="mp-card">
                    <div className="mp-card-body">
                        <h2 className="font-semibold text-slate-900">
                            Generation controls
                        </h2>
                        <div className="mt-4 grid gap-3">
                            {abilities?.synchronize ? (
                                <Button
                                    disabled={processing}
                                    onClick={() =>
                                        action(
                                            `/operational/contracts/${contract.id}/synchronize`,
                                            { lock_version: contract.lockVersion },
                                        )
                                    }
                                >
                                    Synchronize
                                </Button>
                            ) : null}
                            {abilities?.generate ? (
                                <>
                                    <SelectInput
                                        label="Planning year"
                                        value={selectedYear}
                                        onChange={(event) =>
                                            setSelectedYear(
                                                event.target.value
                                                    ? Number(event.target.value)
                                                    : "",
                                            )
                                        }
                                    >
                                        <option value="">Select year</option>
                                        {planningYears.map((year) => (
                                            <option key={year.value} value={year.value}>
                                                {year.label}
                                            </option>
                                        ))}
                                    </SelectInput>
                                    <Button
                                        variant="secondary"
                                        disabled={processing || selectedYear === ""}
                                        onClick={() =>
                                            action(
                                                `/operational/contracts/${contract.id}/generate/${selectedYear}`,
                                                { lock_version: contract.lockVersion },
                                            )
                                        }
                                    >
                                        Generate selected year
                                    </Button>
                                </>
                            ) : null}
                            {!abilities?.synchronize && !abilities?.generate ? (
                                <p className="text-sm text-slate-500">
                                    You do not have access to generation actions.
                                </p>
                            ) : null}
                        </div>
                    </div>
                </section>
            </div>

            <section className="mt-6">
                <h2 className="mb-3 text-lg font-semibold text-slate-900">Terms</h2>
                {contract.terms.length > 0 ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {contract.terms.map((term) => (
                            <TermCard
                                key={term.id}
                                term={term}
                                canDelete={abilities?.deleteTerm === true}
                                onDelete={() => {
                                    setDeletionReason("");
                                    setDeleteTerm(term);
                                }}
                            />
                        ))}
                    </div>
                ) : (
                    <EmptyState title="No current terms">
                        This contract has no current generation terms.
                    </EmptyState>
                )}
            </section>

            <section className="mt-6">
                <h2 className="mb-3 text-lg font-semibold text-slate-900">
                    Generated expenses
                </h2>
                {generatedExpenses.length > 0 ? (
                    <div className="mp-card operational-table-scroll overflow-x-auto">
                        <table className="mp-table">
                            <thead>
                                <tr>
                                    <th>Occurrence</th>
                                    <th>Expense</th>
                                    <th>Confirmation</th>
                                    <th>Management</th>
                                    <th>Gross</th>
                                </tr>
                            </thead>
                            <tbody>
                                {generatedExpenses.map((expense) => {
                                    const confirmationState =
                                        expense.confirmationState ?? expense.state;
                                    return (
                                    <tr key={`${expense.sourceKey}-${expense.id}`}>
                                        <td>
                                            {expense.periodLabel ??
                                                expense.occurrenceDate ??
                                                "—"}
                                        </td>
                                        <td>
                                            <Link
                                                href={
                                                    expense.href ??
                                                    `/operational/expenses/${expense.id}`
                                                }
                                                className="font-semibold text-brand-700"
                                            >
                                                {expense.title}
                                            </Link>
                                        </td>
                                        <td>
                                            <Badge
                                                tone={
                                                    confirmationState === "confirmed"
                                                        ? "green"
                                                        : "amber"
                                                }
                                            >
                                                {confirmationState === "confirmed"
                                                    ? "Confirmed"
                                                    : "To confirm"}
                                            </Badge>
                                        </td>
                                        <td>
                                            {expense.isSystemManaged === undefined
                                                ? "—"
                                                : expense.isSystemManaged
                                                  ? "Contract managed"
                                                  : "User authoritative"}
                                        </td>
                                        <td>{expense.gross ?? "—"}</td>
                                    </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <EmptyState title="No generated expenses">
                        Synchronize or generate a valid planning year to materialize occurrences.
                    </EmptyState>
                )}
            </section>

            <section className="mt-6 grid gap-5 xl:grid-cols-2">
                <div>
                    <h2 className="mb-3 text-lg font-semibold text-slate-900">
                        Suppressed occurrences
                    </h2>
                    {suppressedOccurrences.length > 0 ? (
                        <div className="grid gap-3">
                            {suppressedOccurrences.map((occurrence) => (
                                <SuppressedCard
                                    key={occurrence.sourceKey}
                                    occurrence={occurrence}
                                    processing={processing}
                                    canResume={abilities?.resume === true}
                                    onResume={(generate) =>
                                        action(
                                            `/operational/contracts/${contract.id}/occurrences/${encodeURIComponent(occurrence.sourceKey)}/${generate ? "resume-and-generate" : "resume"}`,
                                        )
                                    }
                                />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No suppressed occurrences">
                            All eligible occurrences may currently be generated.
                        </EmptyState>
                    )}
                </div>
                <div>
                    <h2 className="mb-3 text-lg font-semibold text-slate-900">
                        Revision activity
                    </h2>
                    {revisionActivity.length > 0 ? (
                        <div className="mp-card divide-y divide-slate-200">
                            {revisionActivity.map((activity, index) => (
                                <div key={activity.id ?? index} className="p-4">
                                    <div className="flex justify-between gap-4">
                                        <p className="font-semibold text-slate-900">
                                            {activity.operation}
                                        </p>
                                        <time className="text-xs text-slate-500">
                                            {activity.timestamp}
                                        </time>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {activity.actor ?? "System"}
                                        {activity.summary ? ` · ${activity.summary}` : ""}
                                    </p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No revision activity">
                            No revision summary is available for this contract.
                        </EmptyState>
                    )}
                </div>
            </section>

            <ConfirmModal
                open={deleteContract}
                title="Delete contract"
                confirmLabel="Delete contract"
                busy={processing || (deletionReasonRequired && !deletionReason.trim())}
                onClose={() => setDeleteContract(false)}
                onConfirm={destroyContract}
            >
                <p>
                    Contract deletion is terminal. Existing generated expenses remain and become user-authoritative; future generation stops.
                </p>
                <DeletionReason
                    required={deletionReasonRequired}
                    value={deletionReason}
                    onChange={setDeletionReason}
                />
            </ConfirmModal>

            <ConfirmModal
                open={deleteTerm !== null}
                title="Delete contract term"
                confirmLabel="Delete term"
                busy={processing || (deletionReasonRequired && !deletionReason.trim())}
                onClose={() => setDeleteTerm(null)}
                onConfirm={destroyTerm}
            >
                <p>
                    Term deletion is terminal and stops its future generation. Existing generated expenses are preserved.
                </p>
                <DeletionReason
                    required={deletionReasonRequired}
                    value={deletionReason}
                    onChange={setDeletionReason}
                />
            </ConfirmModal>
        </AppLayout>
    );
}

function TermCard({
    term,
    canDelete,
    onDelete,
}: {
    term: ContractTermRecord;
    canDelete: boolean;
    onDelete: () => void;
}) {
    return (
        <article className="mp-card p-5">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <div className="flex flex-wrap gap-2">
                        <Badge tone="blue">{term.billingCycle}</Badge>
                        {term.autoRenew ? <Badge tone="green">Auto-renew</Badge> : null}
                    </div>
                    <h3 className="mt-3 font-semibold text-slate-900">
                        {term.effectiveStart} – {term.effectiveEnd}
                    </h3>
                </div>
                {canDelete ? (
                    <button
                        type="button"
                        className="text-sm font-semibold text-red-700"
                        onClick={onDelete}
                    >
                        Delete
                    </button>
                ) : null}
            </div>
            <dl className="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-4">
                <Detail label="Net" value={term.net} />
                <Detail label="VAT" value={term.vat} />
                <Detail label="Gross" value={term.gross} />
                <Detail
                    label="Occurrence"
                    value={term.occurrenceAmount ?? term.gross}
                />
            </dl>
        </article>
    );
}

function SuppressedCard({
    occurrence,
    processing,
    canResume,
    onResume,
}: {
    occurrence: SuppressedOccurrence;
    processing: boolean;
    canResume: boolean;
    onResume: (generate: boolean) => void;
}) {
    return (
        <article className="mp-card p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="font-semibold text-slate-900">
                        {occurrence.periodLabel ??
                            occurrence.occurrenceDate ??
                            occurrence.sourceKey}
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                        {occurrence.suppressedBy ?? "Unknown actor"}
                        {occurrence.suppressedAt
                            ? ` · ${occurrence.suppressedAt}`
                            : ""}
                    </p>
                </div>
                {canResume ? (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="secondary"
                            disabled={processing}
                            onClick={() => onResume(false)}
                        >
                            Resume
                        </Button>
                        <Button disabled={processing} onClick={() => onResume(true)}>
                            Resume and generate
                        </Button>
                    </div>
                ) : null}
            </div>
        </article>
    );
}

function DeletionReason({
    required,
    value,
    onChange,
}: {
    required: boolean;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="mt-4">
            <Textarea
                label={`Deletion reason${required ? "" : " (optional)"}`}
                required={required}
                maxLength={500}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <p className="mt-1 text-right text-xs text-slate-500">
                {value.length}/500
            </p>
        </div>
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

function dateRange(start?: string | null, end?: string | null) {
    if (!start && !end) return "—";
    return `${start ?? "…"} – ${end ?? "…"}`;
}
