import Button from "../ui/button/Button";
import type { PaginationMeta } from "../../api/client";

interface ExpensePaginationProps {
  meta: PaginationMeta;
  onPageChange: (page: number) => void;
  disabled?: boolean;
}

export default function ExpensePagination({
  meta,
  onPageChange,
  disabled = false,
}: ExpensePaginationProps) {
  if (meta.last_page <= 1) {
    return null;
  }

  const pageStart = Math.max(1, Math.min(meta.current_page - 2, meta.last_page - 4));
  const pageEnd = Math.min(meta.last_page, pageStart + 4);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 dark:border-white/[0.05]">
      <p className="text-sm text-gray-500 dark:text-gray-400">
        Pagina {meta.current_page} di {meta.last_page} · {meta.total} risultati
      </p>
      <div className="flex items-center gap-2">
        <Button
          size="sm"
          variant="outline"
          onClick={() => onPageChange(meta.current_page - 1)}
          disabled={disabled || meta.current_page <= 1}
        >
          Precedente
        </Button>
        {Array.from({ length: pageEnd - pageStart + 1 }, (_, index) => {
          const page = pageStart + index;
          return (
            <button
              key={page}
              type="button"
              aria-current={page === meta.current_page ? "page" : undefined}
              aria-label={`Pagina ${page}`}
              disabled={disabled}
              onClick={() => onPageChange(page)}
              className={`rounded-lg px-3 py-2 text-sm font-medium transition ${
                page === meta.current_page
                  ? "bg-brand-500 text-white"
                  : "text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.05]"
              } disabled:cursor-not-allowed disabled:opacity-50`}
            >
              {page}
            </button>
          );
        })}
        <Button
          size="sm"
          variant="outline"
          onClick={() => onPageChange(meta.current_page + 1)}
          disabled={disabled || meta.current_page >= meta.last_page}
        >
          Successiva
        </Button>
      </div>
    </div>
  );
}
