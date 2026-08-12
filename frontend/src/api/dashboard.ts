import { apiClient, type DataEnvelope } from "./client";
import type { ProjectionTotals } from "./projection";

export interface DashboardProjectionBucket {
  key: string;
  label: string;
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
}

export interface DashboardRecentExpense {
  expense_id: number;
  title: string;
  updated_at: string;
  cost_center_name: string;
  project_title: string | null;
  vendor_summary: string | null;
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
}

export interface DashboardListItem {
  id: number;
  label: string;
  date?: string;
  state?: string;
  event_type?: string;
}

export interface ReportingDataset {
  planning_year_id: number;
  economic_year_label: number;
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
  has_economic_data: boolean;
  expense_count: number;
  recent_expenses: DashboardRecentExpense[];
  monthly: DashboardProjectionBucket[];
  by_type: DashboardProjectionBucket[];
  by_cost_center: DashboardProjectionBucket[];
  by_project: DashboardProjectionBucket[];
  generated_contract_planning: DashboardListItem[];
  active_contracts: DashboardListItem[];
  upcoming_contract_events: DashboardListItem[];
}

export interface DashboardQuery {
  planning_year_id: number;
}

export async function getDashboard(params: DashboardQuery): Promise<ReportingDataset> {
  const response = await apiClient.get<DataEnvelope<ReportingDataset>>("/api/v1/dashboard", { params });
  return response.data.data;
}
