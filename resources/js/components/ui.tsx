import { Link, router, usePage } from "@inertiajs/react";
import { type FormEvent, type ReactNode, useEffect, useState } from "react";
import type { BreadcrumbItem, PaginationLink, SharedPageProps } from "../types";

export const cx = (...classes: Array<string | false | null | undefined>) =>
    classes.filter(Boolean).join(" ");

export function Button({
    children,
    className,
    variant = "primary",
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: "primary" | "secondary" | "danger";
}) {
    return (
        <button
            className={cx("mp-button", `mp-button-${variant}`, className)}
            {...props}
        >
            {children}
        </button>
    );
}
export function TextInput({
    label,
    error,
    className,
    ...props
}: React.InputHTMLAttributes<HTMLInputElement> & {
    label?: string;
    error?: string;
}) {
    return (
        <label className="block">
            {label && <span className="mp-label">{label}</span>}
            <input
                className={cx(
                    "mp-input",
                    error &&
                        "border-red-500 focus:border-red-500 focus:ring-red-500",
                    className,
                )}
                {...props}
            />
            {error && (
                <span className="mt-1 block text-xs text-red-600">{error}</span>
            )}
        </label>
    );
}
export function SelectInput({
    label,
    error,
    children,
    ...props
}: React.SelectHTMLAttributes<HTMLSelectElement> & {
    label?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="block">
            {label && <span className="mp-label">{label}</span>}
            <select className="mp-input" {...props}>
                {children}
            </select>
            {error && (
                <span className="mt-1 block text-xs text-red-600">{error}</span>
            )}
        </label>
    );
}
export function Textarea({
    label,
    error,
    ...props
}: React.TextareaHTMLAttributes<HTMLTextAreaElement> & {
    label?: string;
    error?: string;
}) {
    return (
        <label className="block">
            {label && <span className="mp-label">{label}</span>}
            <textarea className="mp-input min-h-24" {...props} />
            {error && (
                <span className="mt-1 block text-xs text-red-600">{error}</span>
            )}
        </label>
    );
}
export function Badge({
    children,
    tone = "slate",
}: {
    children: ReactNode;
    tone?: "slate" | "green" | "red" | "amber" | "blue";
}) {
    const tones = {
        slate: "bg-slate-100 text-slate-700",
        green: "bg-emerald-50 text-emerald-700",
        red: "bg-red-50 text-red-700",
        amber: "bg-amber-50 text-amber-700",
        blue: "bg-blue-50 text-blue-700",
    };
    return (
        <span
            className={cx(
                "inline-flex rounded-full px-2.5 py-1 text-xs font-medium",
                tones[tone],
            )}
        >
            {children}
        </span>
    );
}
export function PageHeader({
    title,
    description,
    crumbs,
    action,
}: {
    title: string;
    description?: string;
    crumbs?: BreadcrumbItem[];
    action?: ReactNode;
}) {
    return (
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <nav
                    aria-label="Breadcrumb"
                    className="mb-2 flex gap-2 text-sm text-slate-500"
                >
                    {crumbs?.map((c, i) => (
                        <span key={c.label} className="flex gap-2">
                            {i > 0 && <span>/</span>}
                            {c.href ? (
                                <Link
                                    href={c.href}
                                    className="hover:text-brand-700"
                                >
                                    {c.label}
                                </Link>
                            ) : (
                                c.label
                            )}
                        </span>
                    ))}
                </nav>
                <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm text-slate-500">{description}</p>
                )}
            </div>
            {action}
        </div>
    );
}
export function FlashMessages() {
    const { flash, diagnostics } = usePage<SharedPageProps>().props;
    return (
        <>
            {flash.success && (
                <div
                    role="status"
                    className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div
                    role="alert"
                    className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    {flash.error}
                    {diagnostics.correlationId && (
                        <span className="ml-1 text-xs">
                            Reference: {diagnostics.correlationId}
                        </span>
                    )}
                </div>
            )}
        </>
    );
}
export function EmptyState({
    title = "No records yet",
    children,
}: {
    title?: string;
    children?: ReactNode;
}) {
    return (
        <div className="mp-card p-10 text-center">
            <div className="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-brand-50 text-brand-700">
                ○
            </div>
            <h2 className="font-semibold text-slate-900">{title}</h2>
            {children && (
                <div className="mt-2 text-sm text-slate-500">{children}</div>
            )}
        </div>
    );
}
export function LoadingState() {
    return (
        <div className="mp-card p-8 text-center text-sm text-slate-500">
            Loading…
        </div>
    );
}
export function ErrorState({
    message = "We could not load this information.",
}: {
    message?: string;
}) {
    return (
        <div
            role="alert"
            className="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-800"
        >
            {message}
        </div>
    );
}
export function Pagination({ links }: { links?: PaginationLink[] }) {
    if (!links?.length) return null;
    return (
        <nav aria-label="Pagination" className="flex flex-wrap gap-1">
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
                    disabled={!link.url}
                    onClick={() => link.url && router.get(link.url)}
                    className={cx(
                        "rounded-md px-3 py-2 text-sm",
                        link.active
                            ? "bg-brand-700 text-white"
                            : "text-slate-600 hover:bg-slate-100",
                        !link.url && "opacity-40",
                    )}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
}
export function ConfirmModal({
    open,
    title,
    children,
    confirmLabel = "Confirm",
    onClose,
    onConfirm,
    busy = false,
}: {
    open: boolean;
    title: string;
    children: ReactNode;
    confirmLabel?: string;
    onClose: () => void;
    onConfirm: () => void;
    busy?: boolean;
}) {
    useEffect(() => {
        const close = (e: KeyboardEvent) => e.key === "Escape" && onClose();
        if (open) window.addEventListener("keydown", close);
        return () => window.removeEventListener("keydown", close);
    }, [open, onClose]);
    if (!open) return null;
    return (
        <div
            className="fixed inset-0 z-50 grid place-items-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
        >
            <button
                data-close-modal
                aria-label="Close dialog"
                onClick={onClose}
                className="absolute inset-0 bg-slate-950/45"
            />
            <div className="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2
                    id="modal-title"
                    className="text-lg font-bold text-slate-900"
                >
                    {title}
                </h2>
                <div className="mt-3 text-sm text-slate-600">{children}</div>
                <div className="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        variant="danger"
                        disabled={busy}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
export function SearchForm({
    value,
    onChange,
    placeholder = "Search",
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
}) {
    const submit = (e: FormEvent) => e.preventDefault();
    return (
        <form onSubmit={submit}>
            <TextInput
                aria-label={placeholder}
                placeholder={placeholder}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
        </form>
    );
}
