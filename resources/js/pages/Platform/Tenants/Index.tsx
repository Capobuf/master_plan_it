import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AppLayout from "../../../layouts/AppLayout";
import {
    Badge,
    Button,
    ConfirmModal,
    EmptyState,
    PageHeader,
    TextInput,
} from "../../../components/ui";
type Tenant = {
    id: number;
    name: string;
    code: string;
    currency: string;
    language: string;
    timezone: string;
    vatRate?: string;
    state: string;
    lockVersion: number;
};
export default function TenantIndex({
    tenants = [],
    abilities = {} as any,
}: {
    tenants?: Tenant[];
    abilities?: any;
}) {
    const [target, setTarget] = useState<Tenant | null>(null);
    const form = useForm({ confirmation_code: "", lock_version: 0 });
    const deactivate = () =>
        target &&
        form.post(`/platform/tenants/${target.id}/deactivate`, {
            onSuccess: () => {
                setTarget(null);
                form.reset();
            },
        });
    return (
        <AppLayout>
            <Head title="Tenants" />
            <PageHeader
                title="Tenants"
                description="Manage platform tenant workspaces."
                crumbs={[{ label: "Tenants" }]}
                action={
                    abilities.create && (
                        <Link
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3.5 text-sm text-white shadow-theme-xs transition hover:bg-brand-600"
                            href="/platform/tenants/create"
                        >
                            Add tenant
                        </Link>
                    )
                }
            />
            {tenants.length ? (
                <div className="mp-card operational-table-scroll overflow-x-auto">
                    <table className="mp-table">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Code</th>
                                <th>Currency</th>
                                <th>Language</th>
                                <th>Status</th>
                                <th>
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {tenants.map((t) => (
                                <tr key={t.id}>
                                    <td>
                                        <div className="font-semibold text-slate-900">
                                            {t.name}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {t.timezone}
                                        </div>
                                    </td>
                                    <td>{t.code}</td>
                                    <td>{t.currency}</td>
                                    <td>{t.language}</td>
                                    <td>
                                        <Badge
                                            tone={
                                                t.state === "active"
                                                    ? "green"
                                                    : "slate"
                                            }
                                        >
                                            {t.state}
                                        </Badge>
                                    </td>
                                    <td>
                                        <div className="flex gap-3">
                                            {abilities.enter &&
                                                t.state === "active" && (
                                                    <button
                                                        data-enter-tenant={t.id}
                                                        onClick={() =>
                                                            router.post(
                                                                `/platform/tenants/${t.id}/enter`,
                                                            )
                                                        }
                                                        className="font-semibold text-brand-700"
                                                    >
                                                        Enter
                                                    </button>
                                                )}
                                            {abilities.update && (
                                                <Link
                                                    className="font-semibold text-brand-700"
                                                    href={`/platform/tenants/${t.id}/edit`}
                                                >
                                                    Edit
                                                </Link>
                                            )}
                                            {abilities.deactivate &&
                                                t.state === "active" && (
                                                    <button
                                                        onClick={() => {
                                                            setTarget(t);
                                                            form.setData(
                                                                "lock_version",
                                                                t.lockVersion,
                                                            );
                                                        }}
                                                        className="font-semibold text-red-700"
                                                    >
                                                        Deactivate
                                                    </button>
                                                )}
                                            {abilities.reactivate &&
                                                t.state === "inactive" && (
                                                    <button
                                                        onClick={() =>
                                                            router.post(
                                                                `/platform/tenants/${t.id}/reactivate`,
                                                                {
                                                                    lock_version:
                                                                        t.lockVersion,
                                                                },
                                                            )
                                                        }
                                                        className="font-semibold text-emerald-700"
                                                    >
                                                        Reactivate
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
                <EmptyState title="No tenants yet">
                    Create the first tenant workspace to get started.
                </EmptyState>
            )}
            <ConfirmModal
                open={!!target}
                title="Deactivate tenant"
                confirmLabel="Deactivate"
                busy={form.processing}
                onClose={() => setTarget(null)}
                onConfirm={deactivate}
            >
                <p>
                    This will stop tenant access. Type{" "}
                    <strong>{target?.code}</strong> to confirm.
                </p>
                <div className="mt-4">
                    <TextInput
                        aria-label="Tenant code"
                        value={form.data.confirmation_code}
                        onChange={(e) =>
                            form.setData("confirmation_code", e.target.value)
                        }
                        error={form.errors.confirmation_code}
                    />
                </div>
            </ConfirmModal>
        </AppLayout>
    );
}
