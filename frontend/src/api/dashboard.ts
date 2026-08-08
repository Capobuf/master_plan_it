import { apiClient, type DataEnvelope } from "./client";

export interface ReportingScope {
  tenant_id?: number;
  planning_year_id?: number;
  year?: number;
  currency?: string;
  official_basis?: string;
}

export interface ReportingSummary {
  official_basis?: string;
  currency?: string;
  amounts?: Record<string, string>;
}

export interface ReportingDataset {
  scope?: ReportingScope | null;
  summary?: ReportingSummary | null;
  monthly?: Record<string, string>;
  by_type?: Record<string, string>;
  by_cost_center?: Record<string, string>;
  cost_center_id?: number | null;
  has_economic_data?: boolean;
  year_options?: Record<string, unknown>[];
  selected_year_id?: number | null;
  ancillary?: Record<string, Record<string, unknown>[]>;
}

export interface DashboardQuery {
  planning_year_id?: number;
  year?: number;
}

export async function getDashboard(
  params: DashboardQuery = {},
): Promise<ReportingDataset> {
  const response = await apiClient.get<DataEnvelope<ReportingDataset>>(
    "/api/v1/dashboard",
    { params },
  );

  return response.data.data;
}
