interface ComponentCardProps {
  title: string;
  children: React.ReactNode;
  className?: string; // Additional custom classes for styling
  desc?: string; // Description text
  compact?: boolean;
  actions?: React.ReactNode;
}

const ComponentCard: React.FC<ComponentCardProps> = ({
  title,
  children,
  className = "",
  desc = "",
  compact = false,
  actions,
}) => {
  return (
    <div
      className={`rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] ${className}`}
    >
      {/* Card Header */}
      <div className={`${compact ? "px-4 py-4 sm:px-5" : "px-6 py-5"} flex flex-wrap items-start justify-between gap-3`}>
        <div className="min-w-0 flex-1">
          <h3 className={`${compact ? "text-base sm:text-lg" : "text-lg"} font-semibold text-gray-800 dark:text-white/90`}>
            {title}
          </h3>
          {desc && (
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {desc}
            </p>
          )}
        </div>
        {actions ? <div className="shrink-0">{actions}</div> : null}
      </div>

      {/* Card Body */}
      <div className={`${compact ? "p-4" : "p-4 sm:p-6"} border-t border-gray-100 dark:border-gray-800`}>
        <div className={compact ? "space-y-3" : "space-y-6"}>{children}</div>
      </div>
    </div>
  );
};

export default ComponentCard;
