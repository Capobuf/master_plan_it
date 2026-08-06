import type { ReactNode } from "react";

type AlertProps = {
    variant: "success" | "error" | "warning" | "info";
    title: string;
    message: ReactNode;
};

/** TailAdmin React Alert component. */
export default function Alert({ variant, title, message }: AlertProps) {
    const styles = {
        success: "border-success-500 bg-success-50 text-success-600 dark:border-success-500/30 dark:bg-success-500/15",
        error: "border-error-500 bg-error-50 text-error-600 dark:border-error-500/30 dark:bg-error-500/15",
        warning: "border-warning-500 bg-warning-50 text-warning-600 dark:border-warning-500/30 dark:bg-warning-500/15",
        info: "border-blue-light-500 bg-blue-light-50 text-blue-light-500 dark:border-blue-light-500/30 dark:bg-blue-light-500/15",
    };

    return (
        <div role={variant === "error" ? "alert" : "status"} className={`rounded-xl border p-4 ${styles[variant]}`}>
            <div className="flex items-start gap-3">
                <span aria-hidden="true" className="mt-0.5 text-lg leading-none">{variant === "success" ? "✓" : variant === "error" ? "!" : variant === "warning" ? "⚠" : "i"}</span>
                <div className="min-w-0">
                    <h4 className="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">{title}</h4>
                    <div className="text-sm text-gray-500 dark:text-gray-400">{message}</div>
                </div>
            </div>
        </div>
    );
}
