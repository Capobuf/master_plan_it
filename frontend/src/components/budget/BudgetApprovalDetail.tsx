import { Link } from "react-router";
import { useMemo, useState } from "react";
import type { ApprovalContributor, BudgetApprovalDetail as BudgetApprovalDetailModel } from "../../api/budget";
import { formatDate, formatDateTime, formatMoney } from "../../presentation/formatters";
import { Modal } from "../ui/modal";
import Badge from "../ui/badge/Badge";
import Button from "../ui/button/Button";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

function withPlanningYearContext(href: string, planningYearId: number): string {
  const [pathAndQuery, hash = ""] = href.split("#", 2);
  const [path, query = ""] = pathAndQuery.split("?", 2);
  const params = new URLSearchParams(query);
  params.set("planning_year_id", String(planningYearId));
  return `${path}?${params.toString()}${hash ? `#${hash}` : ""}`;
}

function evidenceHrefToSpaHref(href: string): string | null {
  const match = /^\/api\/v1\/(expenses|plafonds)\/([1-9]\d*)$/.exec(href);
  if (match === null) return null;
  return match[1] === "expenses" ? `/spese/${match[2]}` : `/plafonds/${match[2]}`;
}

function storedContributorLabel(contributor: ApprovalContributor): string {
  return contributor.row ? `${contributor.expense.title} · ${contributor.row.description}` : contributor.expense.title;
}

function SourceLink({ contributor, planningYearId }: { contributor: ApprovalContributor; planningYearId: number }) {
  const label = storedContributorLabel(contributor);
  const spaHref = contributor.drill_down.href === null ? null : evidenceHrefToSpaHref(contributor.drill_down.href);
  if (!contributor.drill_down.authorized || spaHref === null) {
    return <span>{label} <span className="text-xs text-gray-500 dark:text-gray-400">(dettaglio non autorizzato)</span></span>;
  }
  return <Link className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400" to={withPlanningYearContext(spaHref, planningYearId)}>{label}</Link>;
}

function FrozenDimensions({ contributor }: { contributor: ApprovalContributor }) {
  const dimensions = [
    `Centro di costo: ${contributor.dimensions.cost_center.name}`,
    contributor.dimensions.vendor ? `Fornitore: ${contributor.dimensions.vendor.name}` : null,
    contributor.dimensions.project ? `Progetto: ${contributor.dimensions.project.title}` : null,
    contributor.dimensions.contract ? `Contratto: ${contributor.dimensions.contract.title}` : null,
  ].filter((value): value is string => value !== null);
  return <ul className="space-y-1 text-xs text-gray-600 dark:text-gray-300">{dimensions.map((dimension) => <li key={dimension}>{dimension}</li>)}</ul>;
}

function FullMeasures({ amount, currency }: { amount: ApprovalContributor["amount"]; currency: string }) {
  return <dl className="grid min-w-48 grid-cols-2 gap-x-3 gap-y-1 text-xs">
    <div><dt className="text-gray-500 dark:text-gray-400">Netto</dt><dd>{formatMoney(amount.net, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">IVA</dt><dd>{formatMoney(amount.vat, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Lordo</dt><dd>{formatMoney(amount.gross, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Valore ufficiale</dt><dd className="font-medium">{formatMoney(amount.official, currency)}</dd></div>
  </dl>;
}

function sumDecimalStrings(values: string[]): string {
  const parsed = values.map((value) => {
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(value);
    if (match === null) return null;
    return { negative: match[1] === "-", integer: match[2], fraction: match[3] ?? "" };
  });
  if (parsed.some((value) => value === null)) return "—";
  const safe = parsed as { negative: boolean; integer: string; fraction: string }[];
  const scale = Math.max(0, ...safe.map((value) => value.fraction.length));
  const total = safe.reduce((sum, value) => {
    const magnitude = BigInt(`${value.integer}${value.fraction.padEnd(scale, "0")}`);
    return sum + (value.negative ? -magnitude : magnitude);
  }, 0n);
  const negative = total < 0n;
  const digits = (negative ? -total : total).toString().padStart(scale + 1, "0");
  return scale === 0
    ? `${negative ? "-" : ""}${digits}`
    : `${negative ? "-" : ""}${digits.slice(0, -scale)}.${digits.slice(-scale)}`;
}

function SelectedTotal({ contributors, currency }: { contributors: ApprovalContributor[]; currency: string }) {
  const totals = {
    net: sumDecimalStrings(contributors.map((contributor) => contributor.amount.net)),
    vat: sumDecimalStrings(contributors.map((contributor) => contributor.amount.vat)),
    gross: sumDecimalStrings(contributors.map((contributor) => contributor.amount.gross)),
    official: sumDecimalStrings(contributors.map((contributor) => contributor.amount.official)),
  };
  return <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4" aria-label="Totale selezione">
    <div><dt className="text-gray-500 dark:text-gray-400">Netto selezione</dt><dd className="font-semibold">{formatMoney(totals.net, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">IVA selezione</dt><dd className="font-semibold">{formatMoney(totals.vat, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Lordo selezione</dt><dd className="font-semibold">{formatMoney(totals.gross, currency)}</dd></div>
    <div><dt className="text-gray-500 dark:text-gray-400">Ufficiale selezione</dt><dd className="font-semibold">{formatMoney(totals.official, currency)}</dd></div>
  </dl>;
}

export default function BudgetApprovalDetail({ approval, onClose }: { approval: BudgetApprovalDetailModel; onClose: () => void }) {
  const [filter, setFilter] = useState("");
  const normalizedFilter = filter.trim().toLocaleLowerCase("it-IT");
  const contributors = useMemo(() => approval.contributors.filter((contributor) => {
    if (!normalizedFilter) return true;
    const searchable = [
      storedContributorLabel(contributor), contributor.kind, contributor.dimensions.cost_center.name,
      contributor.dimensions.vendor?.name, contributor.dimensions.project?.title, contributor.dimensions.contract?.title,
    ].filter((value): value is string => Boolean(value)).join(" ").toLocaleLowerCase("it-IT");
    return searchable.includes(normalizedFilter);
  }), [approval.contributors, normalizedFilter]);
  const titleId = `budget-approval-detail-${approval.id}`;

  return <Modal isOpen onClose={onClose} ariaLabelledBy={titleId} className="max-w-6xl p-6 sm:p-8">
    <div className="space-y-6">
      <div className="pr-10">
        <div className="flex flex-wrap items-center gap-3"><h2 id={titleId} className="text-xl font-semibold text-gray-800 dark:text-white/90">Fotografia approvazione {approval.id}</h2><Badge color={approval.status === "active" ? "success" : "warning"}>{approval.status === "active" ? "Attiva" : "Annullata"}</Badge></div>
        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Decisione registrata e componenti storiche in sola lettura.</p>
      </div>
      <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div><dt className="text-gray-500 dark:text-gray-400">Anno di pianificazione</dt><dd className="font-medium">{approval.planning_year.year_label}</dd></div>
        <div><dt className="text-gray-500 dark:text-gray-400">Data efficacia</dt><dd className="font-medium">{formatDate(approval.effective_date)}</dd></div>
        <div><dt className="text-gray-500 dark:text-gray-400">Registrata</dt><dd className="font-medium">{formatDateTime(approval.recorded_at)}</dd></div>
        <div><dt className="text-gray-500 dark:text-gray-400">Approvata da</dt><dd className="font-medium">{approval.approved_by.name}</dd></div>
        <div><dt className="text-gray-500 dark:text-gray-400">Base economica</dt><dd className="font-medium">{approval.basis === "net" ? "Netto" : "Lordo"}</dd></div>
      </dl>
      <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800"><p className="text-sm font-semibold text-gray-800 dark:text-white/90">Previsto completo registrato</p><FullMeasures amount={approval.total} currency={approval.currency} /></div>
      <div className="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800"><p className="text-gray-500 dark:text-gray-400">Composizione</p><p className="mt-1 font-medium text-gray-800 dark:text-white/90">{approval.composition.contributor_count} componenti · {approval.composition.schema_version}</p><p className="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-300">{approval.composition.fingerprint}</p></div>
      {approval.note ? <div><p className="text-sm text-gray-500 dark:text-gray-400">Nota di approvazione</p><p className="mt-1 text-sm text-gray-800 dark:text-white/90">{approval.note}</p></div> : null}
      {approval.annulment ? <div className="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm dark:border-warning-500/30 dark:bg-warning-500/15"><p className="font-semibold text-gray-800 dark:text-white/90">Annullamento registrato</p><p className="mt-1">{formatDateTime(approval.annulment.annulled_at)} · {approval.annulment.annulled_by.name}</p>{approval.annulment.note ? <p className="mt-1">{approval.annulment.note}</p> : null}</div> : null}
      <div className="space-y-3">
        <div className="flex flex-wrap items-end justify-between gap-3"><div><h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Componenti registrati</h3><p className="text-sm text-gray-500 dark:text-gray-400" aria-live="polite">{contributors.length} di {approval.contributors.length} componenti visualizzati</p></div><label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Filtra componenti registrati<input value={filter} onChange={(event) => setFilter(event.target.value)} className="mt-1 block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white" /></label></div>
        <SelectedTotal contributors={contributors} currency={approval.currency} />
        {contributors.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Nessun componente registrato corrisponde al filtro.</p> : <div className="max-w-full overflow-x-auto"><Table><TableHeader><TableRow>{["Componente", "Origine registrata", "Dimensioni congelate", "Importi registrati"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap">{heading}</TableCell>)}</TableRow></TableHeader><TableBody>{contributors.map((contributor) => <TableRow key={contributor.source_identity}><TableCell><SourceLink contributor={contributor} planningYearId={approval.planning_year.id} /></TableCell><TableCell>{contributor.kind === "plafond_allocation" ? "Allocazione Plafond (conteggiata una volta)" : "Pianificazione corrente"}</TableCell><TableCell><FrozenDimensions contributor={contributor} /></TableCell><TableCell><FullMeasures amount={contributor.amount} currency={approval.currency} /></TableCell></TableRow>)}</TableBody></Table></div>}
      </div>
      <div className="flex justify-end"><Button type="button" variant="outline" onClick={onClose}>Chiudi</Button></div>
    </div>
  </Modal>;
}
