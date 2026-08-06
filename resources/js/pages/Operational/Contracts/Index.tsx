import { Head, Link } from "@inertiajs/react";
import { Badge, EmptyState, PageHeader, Pagination } from "../../../components/ui";
import AppLayout from "../../../layouts/AppLayout";
import type { ContractIndexPageProps, ContractListRecord } from "../../../types";

export default function ContractsIndex({
    contracts,
    abilities,
    navigation,
}: ContractIndexPageProps) {
    const rows: ContractListRecord[] = Array.isArray(contracts)
        ? contracts
        : contracts.data;
    const links = Array.isArray(contracts) ? undefined : contracts.links;
    const canCreate = abilities?.create ?? navigation.canCreateContracts;

    return (
        <AppLayout>
            <Head title="Contracts" />
            <PageHeader
                title="Contracts"
                description="Manage contract terms and materialized expense occurrences."
                crumbs={[{ label: "Contracts" }]}
                action={
                    canCreate ? (
                        <Link
                            href="/operational/contracts/create"
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3.5 text-sm text-white shadow-theme-xs transition hover:bg-brand-600"
                        >
                            New contract
                        </Link>
                    ) : undefined
                }
            />

            {rows.length > 0 ? (
                <>
                    <div className="mp-card operational-table-scroll overflow-x-auto">
                        <table className="mp-table">
                            <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Vendor</th>
                                    <th>Cost center</th>
                                    <th>Date range</th>
                                    <th>Terms</th>
                                    <th>Generated</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((contract) => (
                                    <tr key={contract.id}>
                                        <td>
                                            <Link
                                                href={`/operational/contracts/${contract.id}`}
                                                className="font-semibold text-slate-900 hover:text-brand-700"
                                            >
                                                {contract.title}
                                            </Link>
                                        </td>
                                        <td>{contract.vendorName}</td>
                                        <td>{contract.costCenterName}</td>
                                        <td>
                                            {dateRange(
                                                contract.effectiveStart,
                                                contract.effectiveEnd,
                                            )}
                                        </td>
                                        <td>{contract.termCount}</td>
                                        <td>{contract.generatedExpenseCount ?? "—"}</td>
                                        <td>
                                            <Badge tone={contract.active ? "green" : "slate"}>
                                                {contract.active ? "Active" : "Inactive"}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-5">
                        <Pagination links={links} />
                    </div>
                </>
            ) : (
                <EmptyState title="No contracts found">
                    <p>Create a contract to define recurring operational expenses.</p>
                    {canCreate ? (
                        <Link
                            href="/operational/contracts/create"
                            className="mt-4 inline-flex font-semibold text-brand-700"
                        >
                            Create the first contract →
                        </Link>
                    ) : null}
                </EmptyState>
            )}
        </AppLayout>
    );
}

function dateRange(start?: string | null, end?: string | null) {
    if (!start && !end) return "—";
    return `${start ?? "…"} – ${end ?? "…"}`;
}
