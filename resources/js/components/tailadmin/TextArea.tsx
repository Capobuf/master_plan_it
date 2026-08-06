import type { TextareaHTMLAttributes } from "react";

type TextAreaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
    error?: boolean;
    hint?: string;
};

/** TailAdmin React TextArea component, preserving native textarea attributes. */
export default function TextArea({ className = "", disabled = false, error = false, hint, ...props }: TextAreaProps) {
    const stateClass = disabled
        ? "border-gray-300 bg-gray-100 text-gray-500 opacity-50 dark:border-gray-700 dark:bg-gray-800"
        : error
          ? "border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-800"
          : "border-gray-300 bg-transparent text-gray-900 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800";

    return (
        <div className="relative">
            <textarea
                {...props}
                disabled={disabled}
                className={`w-full rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs focus:outline-hidden focus:ring-3 ${stateClass} ${className}`}
            />
            {hint && <p className={`mt-2 text-sm ${error ? "text-error-500" : "text-gray-500 dark:text-gray-400"}`}>{hint}</p>}
        </div>
    );
}
