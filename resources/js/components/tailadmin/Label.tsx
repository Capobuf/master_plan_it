import type { LabelHTMLAttributes, ReactNode } from "react";

export default function Label({ children, className = "", ...props }: LabelHTMLAttributes<HTMLLabelElement> & { children: ReactNode }) {
    return (
        <label {...props} className={`mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400 ${className}`}>
            {children}
        </label>
    );
}
