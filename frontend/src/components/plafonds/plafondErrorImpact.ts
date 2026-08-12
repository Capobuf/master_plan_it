import type { ApiError } from "../../api/client";
import type { PlafondCoveredRow, PlafondImpact } from "../../api/plafonds";
import type { PlafondMeasures } from "../../api/projection";

const record = (value: unknown): value is Record<string, unknown> => typeof value === "object" && value !== null && !Array.isArray(value);

export function plafondImpactFromError(error: ApiError | null, fallbackTitle = "Plafond interessato"): PlafondImpact | null {
  if (!error || error.code !== "PLAFOND_INSUFFICIENT") return null;
  const details = error.details;
  const impact = details.impact;
  if (!record(impact)
    || typeof details.plafond_expense_id !== "number"
    || typeof details.currency !== "string"
    || (details.basis !== "net" && details.basis !== "gross")
    || typeof details.required !== "string"
    || typeof details.shortage !== "string"
    || typeof impact.requested !== "string"
    || !record(impact.current)
    || !record(impact.proposed)) return null;

  const blockingRows = Array.isArray(impact.blocking_rows)
    ? impact.blocking_rows.filter(record).map((row): PlafondCoveredRow => ({
        expense_id: Number(row.expense_id),
        expense_title: String(row.expense_title ?? "Spesa"),
        row_id: Number(row.row_id),
        description: String(row.description ?? "Riga coperta"),
        type: row.type === "estimate" || row.type === "quote" ? row.type : "actual",
        contributes_to_coverage_planned: Boolean(row.contributes_to_coverage_planned),
        contributes_to_consumed: row.contributes_to_consumed === undefined ? true : Boolean(row.contributes_to_consumed),
        date: typeof row.date === "string" ? row.date : null,
        expense_cost_center: row.expense_cost_center as PlafondCoveredRow["expense_cost_center"],
        plafond_cost_center: row.plafond_cost_center as PlafondCoveredRow["plafond_cost_center"],
        amount: row.amount as PlafondCoveredRow["amount"],
      }))
    : [];

  return {
    plafond: { id: details.plafond_expense_id, title: fallbackTitle },
    currency: details.currency,
    basis: details.basis,
    current: impact.current as unknown as PlafondMeasures,
    proposed: impact.proposed as unknown as PlafondMeasures,
    requested: impact.requested,
    shortage: details.shortage,
    can_confirm: false,
    blocking_rows: blockingRows,
  };
}
