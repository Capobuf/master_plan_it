import { router, usePage } from "@inertiajs/react";
import { type FormEvent, type ReactNode } from "react";
import type { BreadcrumbItem, PaginationLink, SharedPageProps } from "../types";
import TailAdminAlert from "./tailadmin/Alert";
import TailAdminBadge from "./tailadmin/Badge";
import TailAdminButton from "./tailadmin/Button";
import TailAdminInput from "./tailadmin/InputField";
import TailAdminLabel from "./tailadmin/Label";
import TailAdminModal from "./tailadmin/Modal";
import TailAdminPageBreadcrumb from "./tailadmin/PageBreadcrumb";
import TailAdminSelect from "./tailadmin/Select";
import TailAdminTextArea from "./tailadmin/TextArea";

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
        <TailAdminButton
            variant={variant === "primary" ? "primary" : "outline"}
            className={cx(
                variant === "danger" &&
                    "!border-error-300 !bg-error-500 !text-white hover:!bg-error-600",
                className,
            )}
            {...props}
        >
            {children}
        </TailAdminButton>
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
            {label && <TailAdminLabel>{label}</TailAdminLabel>}
            <TailAdminInput
                aria-invalid={error ? true : undefined}
                error={Boolean(error)}
                hint={error}
                className={className}
                {...props}
            />
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
            {label && <TailAdminLabel>{label}</TailAdminLabel>}
            <TailAdminSelect
                aria-invalid={error ? true : undefined}
                error={Boolean(error)}
                hint={error}
                {...props}
            >
                {children}
            </TailAdminSelect>
        </label>
    );
}
export function Textarea({
    label,
    error,
    className,
    ...props
}: React.TextareaHTMLAttributes<HTMLTextAreaElement> & {
    label?: string;
    error?: string;
}) {
    return (
        <label className="block">
            {label && <TailAdminLabel>{label}</TailAdminLabel>}
            <TailAdminTextArea
                className={cx("min-h-24", className)}
                error={Boolean(error)}
                hint={error}
                aria-invalid={error ? true : undefined}
                {...props}
            />
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
    const colors = {
        slate: "light",
        green: "success",
        red: "error",
        amber: "warning",
        blue: "info",
    } as const;
    return (
        <TailAdminBadge color={colors[tone]} size="sm">
            {children}
        </TailAdminBadge>
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
        <div className="mb-6">
            <TailAdminPageBreadcrumb title={title} crumbs={crumbs} />
            {(description || action) && (
                <div className="-mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    {description ? <p className="text-theme-sm text-gray-500 dark:text-gray-400">{description}</p> : <span />}
                    {action}
                </div>
            )}
        </div>
    );
}
export function FlashMessages() {
    const { flash, diagnostics } = usePage<SharedPageProps>().props;
    return (
        <>
            {flash.success && (
                <div className="mb-5"><TailAdminAlert variant="success" title="Success" message={flash.success} /></div>
            )}
            {flash.error && (
                <div className="mb-5"><TailAdminAlert variant="error" title="Error" message={<>{flash.error}{diagnostics.correlationId && <span className="ml-1 text-xs">Reference: {diagnostics.correlationId}</span>}</>} /></div>
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
        <TailAdminAlert variant="error" title="Unable to load data" message={message} />
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
    return (
        <TailAdminModal isOpen={open} onClose={onClose} showCloseButton={false} className="max-w-md p-6">
                <h2
                    id="modal-title"
                    className="text-lg font-bold text-gray-800 dark:text-white/90"
                >
                    {title}
                </h2>
                <div className="mt-3 text-sm text-gray-500 dark:text-gray-400">{children}</div>
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
        </TailAdminModal>
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
