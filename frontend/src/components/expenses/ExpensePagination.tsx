import Button from "../ui/button/Button";
import type { PaginationMeta } from "../../api/client";

interface ExpensePaginationProps {
  meta: PaginationMeta;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  disabled?: boolean;
}

export default function ExpensePagination({
  meta,
  onPageChange,
  onPerPageChange,
  disabled = false,
}: ExpensePaginationProps) {
  const pageStart = Math.max(1, Math.min(meta.current_page - 2, meta.last_page - 4));
  const pageEnd = Math.min(meta.last_page, pageStart + 4);
  const resultStart = meta.total === 0 ? 0 : ((meta.current_page - 1) * meta.per_page) + 1;
  const resultEnd = Math.min(meta.current_page * meta.per_page, meta.total);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 dark:border-white/[0.05]">
      <p className="whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {resultStart}–{resultEnd} di {meta.total}
      </p>
      <div className="flex flex-wrap items-center justify-end gap-3">
        <label className="flex shrink-0 items-center gap-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
          Righe per pagina
          <select
            aria-label="Righe per pagina"
            value={meta.per_page}
            onChange={(event) => onPerPageChange(Number(event.target.value))}
            disabled={disabled}
            className="h-9 rounded-lg border border-gray-300 bg-transparent px-2.5 text-sm text-gray-700 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-brand-800"
          >
            {[25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
          </select>
        </label>
        {meta.last_page > 1 ? <div className="flex items-center gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => onPageChange(meta.current_page - 1)} disabled={disabled || meta.current_page <= 1}>Precedente</Button>
          <div className="hidden items-center gap-2 sm:flex">
            {Array.from({ length: pageEnd - pageStart + 1 }, (_, index) => {
              const page = pageStart + index;
              return <Button key={page} type="button" size="sm" disabled={disabled} onClick={() => onPageChange(page)} variant={page === meta.current_page ? "primary" : "outline"} className="min-w-9 px-2.5">{page}</Button>;
            })}
          </div>
          <Button type="button" size="sm" variant="outline" onClick={() => onPageChange(meta.current_page + 1)} disabled={disabled || meta.current_page >= meta.last_page}>Successiva</Button>
        </div> : null}
      </div>
    </div>
  );
}
