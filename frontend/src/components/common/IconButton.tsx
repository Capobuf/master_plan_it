import type { ComponentType, SVGProps } from "react";

interface IconButtonProps {
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  label: string;
  onClick: () => void;
  disabled?: boolean;
  destructive?: boolean;
}

export default function IconButton({ icon: Icon, label, onClick, disabled, destructive = false }: IconButtonProps) {
  return <button type="button" onClick={onClick} disabled={disabled} aria-label={label} title={label} className={`inline-flex h-10 w-10 items-center justify-center rounded-lg border bg-white shadow-theme-xs transition-colors focus:outline-hidden focus:ring-3 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-gray-900 ${destructive ? "border-error-200 text-error-600 hover:bg-error-50 focus:ring-error-500/20 dark:border-error-500/30 dark:text-error-400 dark:hover:bg-error-500/10" : "border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-gray-800 focus:ring-brand-500/20 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05] dark:hover:text-white"}`}>
    <Icon className="h-5 w-5" aria-hidden="true" />
  </button>;
}
