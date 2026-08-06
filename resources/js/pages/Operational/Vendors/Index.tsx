import { Head, Link, router } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import {
    Badge,
    ConfirmModal,
    EmptyState,
    PageHeader,
    Pagination,
} from "../../../components/ui";
import { useState } from "react";
type Vendor = {
    id: number;
    name: string;
    vatNumber?: string;
    email?: string;
    phone?: string;
    active: boolean;
    lockVersion: number;
};
export default function Vendors({
    vendors,
    abilities,
}: {
    vendors?: { data: Vendor[]; links?: any[] } | Vendor[];
    abilities: any;
}) {
    const rows = Array.isArray(vendors) ? vendors : (vendors?.data ?? []);
    const [target, setTarget] = useState<Vendor | null>(null);
    const [operation, setOperation] = useState("");
    const [processing, setProcessing] = useState(false);
    const execute = () => {
        if (!target) return;
        setProcessing(true);
        const options = {
            onSuccess: () => setTarget(null),
            onFinish: () => setProcessing(false),
        };
        if (operation === "delete")
            router.delete(`/operational/vendors/${target.id}`, {
                data: { lock_version: target.lockVersion },
                ...options,
            });
        else
            router.post(
                `/operational/vendors/${target.id}/${operation}`,
                { lock_version: target.lockVersion },
                options,
            );
    };
    return (
        <AppLayout>
            <Head title="Vendors" />
            <PageHeader
                title="Vendors"
                description="Maintain the supplier directory."
                crumbs={[{ label: "Vendors" }]}
                action={
                    abilities.create && (
                        <Link
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3.5 text-sm text-white shadow-theme-xs transition hover:bg-brand-600"
                            href="/operational/vendors/create"
                        >
                            Add vendor
                        </Link>
                    )
                }
            />
            {rows.length ? (
                <div className="mp-card operational-table-scroll overflow-x-auto">
                    <table className="mp-table">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>VAT number</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((v) => (
                                <tr key={v.id}>
                                    <td className="font-semibold text-slate-900">
                                        {v.name}
                                    </td>
                                    <td>{v.vatNumber || "—"}</td>
                                    <td>
                                        <div>{v.email || "—"}</div>
                                        <div className="text-xs text-slate-500">
                                            {v.phone}
                                        </div>
                                    </td>
                                    <td>
                                        <Badge
                                            tone={v.active ? "green" : "slate"}
                                        >
                                            {v.active ? "Active" : "Inactive"}
                                        </Badge>
                                    </td>
                                    <td>
                                        <div className="flex gap-3">
                                            {abilities.update && (
                                                <Link
                                                    className="font-semibold text-brand-700"
                                                    href={`/operational/vendors/${v.id}/edit`}
                                                >
                                                    Edit
                                                </Link>
                                            )}
                                            {abilities.viewRevisions && (
                                                <Link
                                                    className="font-semibold text-brand-700"
                                                    href={`/operational/vendors/${v.id}/history`}
                                                >
                                                    History
                                                </Link>
                                            )}
                                            {v.active &&
                                                abilities.deactivate && (
                                                    <button
                                                        data-deactivate-vendor={v.id}
                                                        className="font-semibold text-red-700"
                                                        onClick={() => {
                                                            setTarget(v);
                                                            setOperation(
                                                                "deactivate",
                                                            );
                                                        }}
                                                    >
                                                        Deactivate
                                                    </button>
                                                )}
                                            {!v.active &&
                                                abilities.reactivate && (
                                                    <button
                                                        className="font-semibold text-emerald-700"
                                                        onClick={() => {
                                                            setTarget(v);
                                                            setOperation(
                                                                "reactivate",
                                                            );
                                                        }}
                                                    >
                                                        Reactivate
                                                    </button>
                                                )}
                                            {abilities.delete && (
                                                <button
                                                    className="font-semibold text-red-700"
                                                    onClick={() => {
                                                        setTarget(v);
                                                        setOperation("delete");
                                                    }}
                                                >
                                                    Delete
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <EmptyState title="No vendors found">
                    Add a vendor when there is one to maintain.
                </EmptyState>
            )}
            <div className="mt-5">
                <Pagination
                    links={!Array.isArray(vendors) ? vendors?.links : undefined}
                />
            </div>
            <ConfirmModal
                open={!!target}
                title={`${operation.charAt(0).toUpperCase()}${operation.slice(1)} vendor`}
                confirmLabel={operation}
                busy={processing}
                onClose={() => setTarget(null)}
                onConfirm={execute}
            >
                Confirm this action for {target?.name}.
            </ConfirmModal>
        </AppLayout>
    );
}
