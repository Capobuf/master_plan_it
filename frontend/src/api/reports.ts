import { apiClient, type PaginationMeta } from "./client";
import type { AnnualBudgetSummary } from "./budget";

export type ReportGrouping = "cost_center" | "project" | "contract" | "vendor" | "expense";

export interface ReportsQuery {
  planning_year_id?: number;
  year?: number;
  cost_center_id?: number;
  group_by?: ReportGrouping;
  as_of?: string;
  page?: number;
  per_page?: number;
}

export interface ReportingGroup {
  key: string;
  label: string;
  group_by: ReportGrouping;
  proposed: string;
  approved: string;
  actual: string;
  residual: string;
  variance: string;
  utilization_percentage: string | null;
  open_expenses: number;
  closed_expenses: number;
  unapproved_actual_expenses: number;
  plafond_expenses: number;
}

export interface ReportsResponse {
  data: ReportingGroup[];
  meta: PaginationMeta;
  mode: "current" | "historical";
  requested_as_of: string | null;
  cutoff_utc: string | null;
  read_only: boolean;
  budget: { planning_year_id: number; year: number; state: string; warning: string | null };
  summary: AnnualBudgetSummary;
  filters: { planning_year_id: number; cost_center_id: number | null; group_by: ReportGrouping };
}

export async function getReports(params: ReportsQuery): Promise<ReportsResponse> {
  const response = await apiClient.get<ReportsResponse>("/api/v1/reports", { params });
  return response.data;
}
