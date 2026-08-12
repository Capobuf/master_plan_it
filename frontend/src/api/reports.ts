import { apiClient, type PaginationMeta } from "./client";
import type { EconomicMeasure, ProjectionTotals } from "./projection";
import type { PlafondMeasures } from "./projection";

export type ReportGrouping = "cost_center" | "project" | "contract" | "vendor" | "expense";

export interface ReportsQuery {
  planning_year_id?: number;
  cost_center_id?: number;
  project_id?: number;
  contract_id?: number;
  vendor_id?: number;
  group_by?: ReportGrouping;
  as_of?: string;
  page?: number;
  per_page?: number;
}

export interface ReportFilters {
  planning_year_id: number;
  cost_center_id: number | null;
  project_id: number | null;
  contract_id: number | null;
  vendor_id: number | null;
  group_by: ReportGrouping;
  as_of: string | null;
}

export interface ReportSummary {
  currency: string;
  official_basis: "net" | "gross";
  proposed: string;
  approved_current: string;
  actual: string;
  residual: string;
  variance: string;
  utilization_percentage: string | null;
  unapproved_actual_expenses: number;
}

export interface ReportLine {
  expense_id: number;
  row_id: number;
  planning_year_id: number;
  economic_year_label: number;
  type: "estimate" | "quote" | "actual";
  is_current_planning: boolean;
  contributes_to_current_planning: boolean;
  description: string;
  notes: string | null;
  spend_date: string | null;
  cost_center_id: number;
  vendor_id: number | null;
  vendor_name: string | null;
  project_id: number | null;
  contract_id: number | null;
  amount: EconomicMeasure;
}

export interface ReportingGroup {
  key: string;
  label: string;
  group_by: ReportGrouping;
  expense_id: number | null;
  cost_center_id: number | null;
  project_id: number | null;
  contract_id: number | null;
  vendor_id: number | null;
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
  proposed: string;
  approved: string;
  actual: string;
  residual: string;
  variance: string;
  utilization_percentage: string | null;
  unapproved_actual_expenses: number;
  plafond_expenses: number;
  lines: ReportLine[];
}

export interface ReportsResponse {
  data: ReportingGroup[];
  meta: PaginationMeta;
  mode: "current" | "historical";
  requested_as_of: string | null;
  cutoff_utc: string | null;
  read_only: boolean;
  budget: {
    planning_year_id: number;
    year: number;
    state: string;
    lock_version: number;
    warning: string | null;
    history_activated_at: string | null;
  };
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
  summary: ReportSummary;
  plafonds?: Array<{
    id: number;
    planning_year_id: number;
    title: string;
    cost_center: { id: number; name: string };
    currency: string;
    basis: "net" | "gross";
    measures: PlafondMeasures;
  }>;
  filters: ReportFilters;
}

export async function getReports(params: ReportsQuery): Promise<ReportsResponse> {
  const response = await apiClient.get<ReportsResponse>("/api/v1/reports", { params });
  return response.data;
}
