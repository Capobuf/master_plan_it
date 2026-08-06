import { Link, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import {
    Button,
    PageHeader,
    SelectInput,
    Textarea,
    TextInput,
} from "../../../components/ui";
import AppLayout from "../../../layouts/AppLayout";
import type {
    BillingCycle,
    ContractFormData,
    ContractFormPageProps,
    ContractFormTerm,
    ContractTermRecord,
} from "../../../types";

let localTermSequence = 0;
const nextTermKey = () => `contract-term-${Date.now()}-${localTermSequence++}`;

export default function ContractForm({
    contract,
    vendors = [],
    costCenters = [],
    defaults,
    method,
}: ContractFormPageProps & { method: "post" | "put" }) {
    const form = useForm<ContractFormData>({
        vendor_id: contract?.vendorId ?? "",
        cost_center_id: contract?.costCenterId ?? "",
        title: contract?.title ?? "",
        active: contract?.active ?? true,
        description: contract?.description ?? "",
        renewal_notice_days:
            contract?.renewalNoticeDays === null ||
            contract?.renewalNoticeDays === undefined
                ? ""
                : String(contract.renewalNoticeDays),
        renewal_date: contract?.renewalDate ?? "",
        renewal_notes: contract?.renewalNotes ?? "",
        lock_version: contract?.lockVersion ?? null,
        terms:
            contract?.terms.map((term) => termToForm(term)) ??
            [newTerm(defaults.vatRate)],
    });

    const updateTerm = (index: number, patch: Partial<ContractFormTerm>) => {
        form.setData(
            "terms",
            form.data.terms.map((term, termIndex) =>
                termIndex === index ? { ...term, ...patch } : term,
            ),
        );
    };

    const removeNewTerm = (index: number) => {
        form.setData(
            "terms",
            form.data.terms.filter((_, termIndex) => termIndex !== index),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (form.processing) return;
        if (method === "post") {
            form.post("/operational/contracts", { preserveScroll: true });
            return;
        }
        form.put(`/operational/contracts/${contract?.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <PageHeader
                title={method === "post" ? "New contract" : "Edit contract"}
                description="Define the contract header and non-overlapping billing terms."
                crumbs={[
                    { label: "Contracts", href: "/operational/contracts" },
                    { label: method === "post" ? "Create" : "Edit" },
                ]}
            />

            <form className="space-y-5" onSubmit={submit}>
                {form.errors.lock_version ? (
                    <div
                        role="alert"
                        className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                    >
                        {form.errors.lock_version} Reload the contract before saving again.
                    </div>
                ) : null}
                <section className="mp-card">
                    <div className="mp-card-body">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Contract header
                        </h2>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
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
                                label="Vendor"
                                required
                                value={form.data.vendor_id}
                                error={form.errors.vendor_id}
                                onChange={(event) =>
                                    form.setData(
                                        "vendor_id",
                                        numberOrEmpty(event.target.value),
                                    )
                                }
                            >
                                <option value="">Select a vendor</option>
                                {vendors.map((vendor) => (
                                    <option key={vendor.value} value={vendor.value}>
                                        {vendor.label}
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
                                {costCenters.map((costCenter) => (
                                    <option key={costCenter.value} value={costCenter.value}>
                                        {costCenter.label}
                                    </option>
                                ))}
                            </SelectInput>
                            <label className="flex min-h-11 items-center gap-3 self-end rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={form.data.active}
                                    onChange={(event) =>
                                        form.setData("active", event.target.checked)
                                    }
                                />
                                Active contract
                            </label>
                            <TextInput
                                label="Renewal notice days (optional)"
                                type="number"
                                min="0"
                                value={form.data.renewal_notice_days}
                                error={form.errors.renewal_notice_days}
                                onChange={(event) =>
                                    form.setData(
                                        "renewal_notice_days",
                                        event.target.value,
                                    )
                                }
                            />
                            <TextInput
                                label="Renewal date (optional)"
                                type="date"
                                value={form.data.renewal_date}
                                error={form.errors.renewal_date}
                                onChange={(event) =>
                                    form.setData("renewal_date", event.target.value)
                                }
                            />
                            <div className="sm:col-span-2">
                                <Textarea
                                    label="Description"
                                    value={form.data.description}
                                    error={form.errors.description}
                                    onChange={(event) =>
                                        form.setData("description", event.target.value)
                                    }
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <Textarea
                                    label="Renewal notes (optional)"
                                    value={form.data.renewal_notes}
                                    error={form.errors.renewal_notes}
                                    onChange={(event) =>
                                        form.setData("renewal_notes", event.target.value)
                                    }
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-end justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Terms
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Occurrence amounts shown after save are calculated by the server.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() =>
                                form.setData("terms", [
                                    ...form.data.terms,
                                    newTerm(defaults.vatRate),
                                ])
                            }
                        >
                            Add term
                        </Button>
                    </div>
                    {form.errors.terms ? (
                        <p role="alert" className="mb-3 text-sm text-red-600">
                            {form.errors.terms}
                        </p>
                    ) : null}
                    <div className="grid gap-4">
                        {form.data.terms.map((term, index) => (
                            <TermEditor
                                key={term.local_key}
                                term={term}
                                index={index}
                                errors={form.errors as Record<string, string>}
                                onChange={(patch) => updateTerm(index, patch)}
                                onRemove={
                                    term.id === null
                                        ? () => removeNewTerm(index)
                                        : undefined
                                }
                            />
                        ))}
                    </div>
                </section>

                <section className="mp-card p-5">
                    <div className="flex flex-wrap items-center justify-end gap-3">
                        <Link
                            href={
                                contract
                                    ? `/operational/contracts/${contract.id}`
                                    : "/operational/contracts"
                            }
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3.5 text-sm text-gray-700 shadow-theme-xs ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? "Saving…"
                                : method === "post"
                                  ? "Create contract"
                                  : "Save changes"}
                        </Button>
                    </div>
                </section>
            </form>
        </AppLayout>
    );
}

function TermEditor({
    term,
    index,
    errors,
    onChange,
    onRemove,
}: {
    term: ContractFormTerm;
    index: number;
    errors: Record<string, string>;
    onChange: (patch: Partial<ContractFormTerm>) => void;
    onRemove?: () => void;
}) {
    const fieldError = (field: keyof ContractFormTerm) =>
        errors[`terms.${index}.${String(field)}`];

    return (
        <article className="mp-card">
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h3 className="font-semibold text-slate-900">Term {index + 1}</h3>
                {onRemove ? (
                    <Button type="button" variant="danger" onClick={onRemove}>
                        Delete term
                    </Button>
                ) : (
                    <span className="text-xs text-slate-500">
                        Delete saved terms from contract detail
                    </span>
                )}
            </div>
            <div className="mp-card-body grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                {fieldError("lock_version") ? (
                    <p
                        role="alert"
                        className="sm:col-span-2 xl:col-span-3 text-sm text-amber-700"
                    >
                        {fieldError("lock_version")} Reload this term before saving again.
                    </p>
                ) : null}
                <TextInput
                    label="Effective start"
                    type="date"
                    required
                    value={term.effective_start}
                    error={fieldError("effective_start")}
                    onChange={(event) =>
                        onChange({ effective_start: event.target.value })
                    }
                />
                <TextInput
                    label="Effective end"
                    type="date"
                    required
                    value={term.effective_end}
                    error={fieldError("effective_end")}
                    onChange={(event) =>
                        onChange({ effective_end: event.target.value })
                    }
                />
                <SelectInput
                    label="Billing cycle"
                    value={term.billing_cycle}
                    error={fieldError("billing_cycle")}
                    onChange={(event) =>
                        onChange({ billing_cycle: event.target.value as BillingCycle })
                    }
                >
                    <option value="monthly">Monthly</option>
                    <option value="annual">Annual</option>
                </SelectInput>
                <TextInput
                    label="Quantity"
                    inputMode="decimal"
                    value={term.quantity}
                    error={fieldError("quantity")}
                    onChange={(event) => onChange({ quantity: event.target.value })}
                />
                <TextInput
                    label="Unit price"
                    inputMode="decimal"
                    value={term.unit_price}
                    error={fieldError("unit_price")}
                    onChange={(event) => onChange({ unit_price: event.target.value })}
                />
                <TextInput
                    label="Entered amount"
                    inputMode="decimal"
                    required
                    value={term.entered_amount}
                    error={fieldError("entered_amount")}
                    onChange={(event) =>
                        onChange({ entered_amount: event.target.value })
                    }
                />
                <TextInput
                    label="VAT rate"
                    inputMode="decimal"
                    value={term.vat_rate}
                    error={fieldError("vat_rate")}
                    onChange={(event) => onChange({ vat_rate: event.target.value })}
                />
                <label className="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={term.amount_includes_vat}
                        onChange={(event) =>
                            onChange({ amount_includes_vat: event.target.checked })
                        }
                    />
                    Entered amount includes VAT
                </label>
                <label className="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={term.auto_renew}
                        onChange={(event) =>
                            onChange({ auto_renew: event.target.checked })
                        }
                    />
                    Auto-renew
                </label>
            </div>
        </article>
    );
}

function newTerm(vatRate: string): ContractFormTerm {
    return {
        local_key: nextTermKey(),
        id: null,
        effective_start: "",
        effective_end: "",
        billing_cycle: "monthly",
        quantity: "1.000000",
        unit_price: "0.000000",
        entered_amount: "0.000000",
        amount_includes_vat: false,
        vat_rate: vatRate,
        auto_renew: false,
        lock_version: null,
    };
}

function termToForm(term: ContractTermRecord): ContractFormTerm {
    return {
        local_key: term.localKey ?? `contract-term-${term.id}`,
        id: term.id,
        effective_start: term.effectiveStart,
        effective_end: term.effectiveEnd,
        billing_cycle: term.billingCycle,
        quantity: term.quantity ?? "1.000000",
        unit_price: term.unitPrice ?? "0.000000",
        entered_amount: term.enteredAmount,
        amount_includes_vat: term.amountIncludesVat,
        vat_rate: term.vatRate,
        auto_renew: term.autoRenew,
        lock_version: term.lockVersion,
    };
}

function numberOrEmpty(value: string): number | "" {
    return value === "" ? "" : Number(value);
}
