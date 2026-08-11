import { apiClient, type PaginationMeta } from "./client";

export type ReportGrouping = "cost_center" | "project" | "contract" | "vendor" | "expense";
export type ReportExpenseState = "open" | "closed";

export interface ReportsQuery {
  planning_year_id?: number;
  year?: number;
  cost_center_id?: number;
  project_id?: number;
  vendor_id?: number;
  state?: ReportExpenseState;
  group_by?: ReportGrouping;
  as_of?: string;
  page?: number;
  per_page?: number;
}

export interface ReportFilters {
  planning_year_id: number;
  cost_center_id: number | null;
  project_id: number | null;
  vendor_id: number | null;
  state: ReportExpenseState | null;
  group_by: ReportGrouping;
}

export interface ReportSummary {
  currency: string;
  official_basis: string;
  proposed: string;
  approved_current: string;
  actual: string;
  residual: string;
  variance: string;
  utilization_percentage: string | null;
  open_expenses: number;
  closed_expenses: number;
  unapproved_actual_expenses: number;
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

export interface ReportVisualizationGroup {
  key: string;
  label: string;
  proposed: string;
  approved: string;
  actual: string;
  residual: string;
  variance: string;
  utilization_percentage: string | null;
}

export interface ReportProposedBreakdown {
  key: string;
  label: string;
  proposed: string;
}

export interface ReportVisualization {
  groups: ReportVisualizationGroup[];
  proposed_breakdown: ReportProposedBreakdown[];
  expense_states: {
    open: number;
    closed: number;
    total: number;
  };
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
  summary: ReportSummary;
  global_plafond_overrun: string;
  visualization: ReportVisualization;
  filters: ReportFilters;
}

export async function getReports(params: ReportsQuery): Promise<ReportsResponse> {
  const response = await apiClient.get<ReportsResponse>("/api/v1/reports", { params });
  return response.data;
}
