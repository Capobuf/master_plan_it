import type { ReportingDataset } from "../../api/dashboard";
import { CheckCircleIcon, DollarLineIcon } from "../../icons";
import { formatMoney } from "../../presentation/formatters";
import EcommerceMetrics, { type EcommerceMetric } from "../ecommerce/EcommerceMetrics";
import Alert from "../ui/alert/Alert";

/** Minimal fifth projection consumer. Comparative dashboard work belongs to Slice 032. */
export default function DashboardView({ dataset }: { dataset: ReportingDataset }) {
  const currency = dataset.currency;
  const totals = dataset.totals;

  if (!dataset.has_economic_data) {
    return <Alert variant="info" title="Nessun dato economico" message="Non sono ancora disponibili dati per l'anno di pianificazione selezionato." />;
  }

  const metrics: EcommerceMetric[] = [
    { label: "Pianificazione corrente", value: formatMoney(totals.current_planning.official, currency), icon: DollarLineIcon },
    { label: "Effettivi", value: formatMoney(totals.actual.official, currency), icon: CheckCircleIcon },
  ];

  return <section className="space-y-4" aria-label="Sintesi economica annuale"><EcommerceMetrics metrics={metrics} /></section>;
}
