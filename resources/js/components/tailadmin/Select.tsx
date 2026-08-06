import type { SelectHTMLAttributes } from "react";

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
    error?: boolean;
    hint?: string;
};

/** TailAdmin React Select component, preserving native select attributes and children. */
export default function Select({ className = "", error = false, hint, ...props }: SelectProps) {
    const stateClass = error
        ? "border-error-500 focus:border-error-300 focus:ring-error-500/20"
        : "border-gray-300 bg-transparent text-gray-800 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800";

    return (
        <div className="relative">
            <select
                {...props}
                className={`h-11 w-full appearance-none rounded-lg border px-4 py-2.5 pr-11 text-sm shadow-theme-xs focus:outline-hidden focus:ring-3 ${stateClass} ${className}`}
            />
            {hint && <p className={`mt-1.5 text-xs ${error ? "text-error-500" : "text-gray-500"}`}>{hint}</p>}
        </div>
    );
}
