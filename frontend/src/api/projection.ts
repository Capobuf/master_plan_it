/** Server-authored annual economic values. Decimal values are intentionally strings. */
export interface EconomicMeasure {
  net: string;
  vat: string;
  gross: string;
  official: string;
}

/** The only totals shape shared by Expense, Budget, Report and Dashboard. */
export interface ProjectionTotals {
  current_planning: EconomicMeasure;
  actual: EconomicMeasure;
}

export interface ProjectionContext {
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
}
