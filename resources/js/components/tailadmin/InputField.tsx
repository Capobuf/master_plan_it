import type { InputHTMLAttributes } from "react";

type InputFieldProps = InputHTMLAttributes<HTMLInputElement> & {
    error?: boolean;
    success?: boolean;
    hint?: string;
};

/** TailAdmin React InputField component, preserving native input attributes. */
export default function InputField({
    className = "",
    disabled = false,
    error = false,
    success = false,
    hint,
    ...props
}: InputFieldProps) {
    const stateClass = disabled
        ? "border-gray-300 bg-gray-100 text-gray-500 opacity-40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
        : error
          ? "border-error-500 focus:border-error-300 focus:ring-error-500/20 dark:border-error-500 dark:text-error-400"
          : success
            ? "border-success-500 focus:border-success-300 focus:ring-success-500/20 dark:border-success-500 dark:text-success-400"
            : "border-gray-300 bg-transparent text-gray-800 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800";

    return (
        <div className="relative">
            <input
                {...props}
                disabled={disabled}
                className={`h-11 w-full appearance-none rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:placeholder:text-white/30 ${stateClass} ${className}`}
            />
            {hint && (
                <p className={`mt-1.5 text-xs ${error ? "text-error-500" : success ? "text-success-500" : "text-gray-500"}`}>
                    {hint}
                </p>
            )}
        </div>
    );
}
