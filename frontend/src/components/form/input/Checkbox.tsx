import type React from "react";

interface CheckboxProps {
  label?: string;
  ariaLabel?: string;
  hideLabel?: boolean;
  checked: boolean;
  className?: string;
  id?: string;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
  error?: boolean;
  hint?: string;
}

const Checkbox: React.FC<CheckboxProps> = ({
  label,
  ariaLabel,
  hideLabel = false,
  checked,
  id,
  onChange,
  className = "",
  disabled = false,
  error = false,
  hint,
}) => {
  return (
    <div>
      <label
        className={`flex items-center space-x-3 group cursor-pointer ${
          disabled ? "cursor-not-allowed opacity-60" : ""
        }`}
      >
        <div className="relative w-5 h-5">
          <input
            id={id}
            type="checkbox"
            className={`w-5 h-5 appearance-none cursor-pointer border checked:border-transparent rounded-md checked:bg-brand-500 disabled:opacity-60 ${error ? "border-error-500 dark:border-error-500" : "border-gray-300 dark:border-gray-700"} ${className}`}
            checked={checked}
            onChange={(e) => onChange(e.target.checked)}
            disabled={disabled}
            aria-label={ariaLabel ?? (hideLabel ? label : undefined)}
            aria-invalid={error || undefined}
            aria-describedby={hint && id ? `${id}-hint` : undefined}
          />
          {checked && (
            <svg
              className="absolute transform -translate-x-1/2 -translate-y-1/2 pointer-events-none top-1/2 left-1/2"
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
              viewBox="0 0 14 14"
              fill="none"
            >
              <path
                d="M11.6666 3.5L5.24992 9.91667L2.33325 7"
                stroke="white"
                strokeWidth="1.94437"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          )}
          {disabled && (
            <svg
              className="absolute transform -translate-x-1/2 -translate-y-1/2 pointer-events-none top-1/2 left-1/2"
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
              viewBox="0 0 14 14"
              fill="none"
            >
              <path
                d="M11.6666 3.5L5.24992 9.91667L2.33325 7"
                stroke="#E4E7EC"
                strokeWidth="2.33333"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          )}
        </div>
        {label && !hideLabel && (
          <span className="text-sm font-medium text-gray-800 dark:text-gray-200">
            {label}
          </span>
        )}
      </label>
      {hint ? <p id={id ? `${id}-hint` : undefined} className={`mt-1.5 text-xs ${error ? "text-error-500" : "text-gray-500 dark:text-gray-400"}`}>{hint}</p> : null}
    </div>
  );
};

export default Checkbox;
