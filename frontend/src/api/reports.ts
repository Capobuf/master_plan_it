import {
  apiClient,
  type PaginationLinks,
  type PaginationMeta,
} from "./client";
import type { ReportingScope, ReportingSummary } from "./dashboard";

export interface ReportsQuery {
  planning_year_id?: number;
  year?: number;
  cost_center_id?: number;
  page?: number;
  per_page?: number;
}

export interface ReportingLine {
  id: number;
  expense_id: number;
  cost_center_id: number;
  cost_center_name: string | null;
  expense_kind: string;
  type: string;
  confirmation_state: string | null;
  net: string;
  vat: string;
  gross: string;
  currency: string;
  official_basis: string;
  funded_plafond_expense_id: number | null;
  spend_date: string | null;
  period_start: string | null;
  period_end: string | null;
  distribution: string | null;
  is_extra: boolean;
}

export interface ReportsResponse {
  data: ReportingLine[];
  meta: PaginationMeta;
  links: PaginationLinks;
  scope?: ReportingScope;
  summary?: ReportingSummary;
  filters?: {
    planning_year_id?: number;
    cost_center_id?: number | null;
  };
}

export async function getReports(
  params: ReportsQuery,
): Promise<ReportsResponse> {
  const response = await apiClient.get<ReportsResponse>("/api/v1/reports", {
    params,
  });

  return response.data;
}
