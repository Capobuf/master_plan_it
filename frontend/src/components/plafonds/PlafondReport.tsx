import { useEffect, useState } from "react";
import { getPlafondReport, type PlafondReportResponse } from "../../api/plafonds";
import { ApiError } from "../../api/client";
import { formatMoney } from "../../presentation/formatters";
import Alert from "../ui/alert/Alert";
import ComponentCard from "../common/ComponentCard";
import PlafondMeasures from "./PlafondMeasures";

export default function PlafondReport({ planningYearId, costCenterId }: { planningYearId: number; costCenterId?: number }) {
  const [response, setResponse] = useState<PlafondReportResponse | null>(null);
  const [error, setError] = useState<ApiError | null>(null);
  useEffect(() => { let active = true; setResponse(null); setError(null); void getPlafondReport({ planning_year_id: planningYearId, ...(costCenterId ? { cost_center_id: costCenterId } : {}) }).then((data) => { if (active) setResponse(data); }).catch((cause: unknown) => { if (active) setError(ApiError.from(cause)); }); return () => { active = false; }; }, [costCenterId, planningYearId]);
  if (error) return <Alert variant="error" title="Report Plafond non disponibile" message={error.message} />;
  if (!response) return <Alert variant="info" title="Caricamento Plafond" message="Recupero delle misure Plafond in corso." />;
  if (response.data.length === 0) return <Alert variant="info" title="Nessun Plafond" message="Non ci sono Plafond per i filtri selezionati." />;
  return <section className="space-y-3" aria-label="Report Plafond">{response.data.map((item) => <ComponentCard key={item.plafond.id} title={item.plafond.title} desc={`Centro Plafond: ${item.plafond.cost_center.name}`} compact><PlafondMeasures measures={item.measures} currency={item.currency} compact /><ul className="mt-3 space-y-1 text-sm">{item.covered_lines.map((line) => <li key={line.row_id} className="flex flex-wrap justify-between gap-2"><span>{line.expense_title} · Centro riga: {line.expense_cost_center.name}</span><strong>{formatMoney(line.amount.official, item.currency)}</strong></li>)}</ul></ComponentCard>)}</section>;
}
