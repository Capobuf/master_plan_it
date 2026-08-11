import type { ComponentType, SVGProps } from "react";

interface IconButtonProps {
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  label: string;
  onClick: () => void;
  disabled?: boolean;
  destructive?: boolean;
}

export default function IconButton({ icon: Icon, label, onClick, disabled, destructive = false }: IconButtonProps) {
  return <button type="button" onClick={onClick} disabled={disabled} aria-label={label} title={label} className={`group relative inline-flex h-9 w-9 items-center justify-center rounded-lg border bg-white shadow-theme-xs transition-colors focus:outline-hidden focus:ring-3 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-gray-900 ${destructive ? "border-error-200 text-error-600 hover:bg-error-50 focus:ring-error-500/20 dark:border-error-500/30 dark:text-error-400 dark:hover:bg-error-500/10" : "border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-gray-800 focus:ring-brand-500/20 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05] dark:hover:text-white"}`}>
    <Icon className="h-4.5 w-4.5" aria-hidden="true" />
    <span role="tooltip" className="pointer-events-none absolute bottom-full right-0 z-50 mb-2 w-max max-w-56 rounded-md bg-gray-900 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-theme-sm transition-opacity group-hover:opacity-100 group-focus:opacity-100 dark:bg-gray-100 dark:text-gray-900">{label}</span>
  </button>;
}
