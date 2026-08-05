import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AppLayout from "../../../layouts/AppLayout";
import type { PaginationLink } from "../../../types";
import {
    Badge,
    Button,
    ConfirmModal,
    EmptyState,
    PageHeader,
    Pagination,
    TextInput,
} from "../../../components/ui";
type User = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    isActive: boolean;
    lockVersion: number;
};
type UsersPageProps = {
    users?: User[] | { data: User[]; links?: PaginationLink[] };
    abilities?: Record<string, boolean>;
};

export default function UsersIndex({
    users,
    abilities = {},
}: UsersPageProps) {
    const rows: User[] = Array.isArray(users) ? users : (users?.data ?? []);
    const [target, setTarget] = useState<User | null>(null);
    const [mode, setMode] = useState("");
    const f = useForm({
        password: "",
        password_confirmation: "",
        lock_version: 0,
    });
    const confirm = () => {
        if (!target) return;

        if (mode === "deactivate") {
            router.post(
                `/operational/users/${target.id}/deactivate`,
                { lock_version: target.lockVersion },
                { onSuccess: () => setTarget(null) },
            );

            return;
        }

        f.put(`/operational/users/${target.id}/password`, {
            onSuccess: () => {
                setTarget(null);
                f.reset();
            },
        });
    };
    return (
        <AppLayout>
            <Head title="Users" />
            <PageHeader
                title="Users"
                description="Manage access for this tenant."
                crumbs={[{ label: "Users" }]}
                action={
                    abilities.create && (
                        <Link
                            href="/operational/users/create"
                            className="mp-button mp-button-primary"
                        >
                            Add user
                        </Link>
                    )
                }
            />
            {rows.length ? (
                <div className="mp-card operational-table-scroll overflow-x-auto">
                    <table className="mp-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((u) => (
                                <tr key={u.id}>
                                    <td>
                                        <div className="font-semibold text-slate-900">
                                            {u.name}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {u.email}
                                        </div>
                                    </td>
                                    <td>{u.roles?.join(", ") || "—"}</td>
                                    <td>
                                        <Badge
                                            tone={
                                                u.isActive ? "green" : "slate"
                                            }
                                        >
                                            {u.isActive ? "Active" : "Inactive"}
                                        </Badge>
                                    </td>
                                    <td>
                                        <div className="flex gap-3">
                                            {abilities.update && (
                                                <Link
                                                    href={`/operational/users/${u.id}/edit`}
                                                    className="font-semibold text-brand-700"
                                                >
                                                    Edit
                                                </Link>
                                            )}
                                            {u.isActive &&
                                                abilities.deactivate && (
                                                    <button
                                                        className="font-semibold text-red-700"
                                                        onClick={() => {
                                                            setTarget(u);
                                                            setMode(
                                                                "deactivate",
                                                            );
                                                        }}
                                                    >
                                                        Deactivate
                                                    </button>
                                                )}
                                            {abilities.resetPassword && (
                                                <button
                                                    className="font-semibold text-brand-700"
                                                    onClick={() => {
                                                        setTarget(u);
                                                        setMode("password");
                                                        f.setData(
                                                            "lock_version",
                                                            u.lockVersion,
                                                        );
                                                    }}
                                                >
                                                    Reset password
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
                <EmptyState title="No users found">
                    Create a tenant user to grant access.
                </EmptyState>
            )}
            <div className="mt-5">
                <Pagination
                    links={!Array.isArray(users) ? users?.links : undefined}
                />
            </div>
            <ConfirmModal
                open={!!target}
                title={
                    mode === "password" ? "Reset password" : "Deactivate user"
                }
                confirmLabel={
                    mode === "password" ? "Update password" : "Deactivate"
                }
                busy={f.processing}
                onClose={() => setTarget(null)}
                onConfirm={confirm}
            >
                {mode === "password" ? (
                    <div className="space-y-3">
                        <TextInput
                            label="New password"
                            type="password"
                            value={f.data.password}
                            onChange={(e) =>
                                f.setData("password", e.target.value)
                            }
                            error={f.errors.password}
                        />
                        <TextInput
                            label="Confirm password"
                            type="password"
                            value={f.data.password_confirmation}
                            onChange={(e) =>
                                f.setData(
                                    "password_confirmation",
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                ) : (
                    `Confirm deactivation of ${target?.name}.`
                )}
            </ConfirmModal>
        </AppLayout>
    );
}
