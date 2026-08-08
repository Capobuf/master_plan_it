import { apiClient, type DataEnvelope } from "./client";
import type { ReportingDataset } from "./dashboard";

export interface BudgetQuery {
  planning_year_id?: number;
  year?: number;
  cost_center_id?: number;
}

export async function getBudget(
  params: BudgetQuery = {},
): Promise<ReportingDataset> {
  const response = await apiClient.get<DataEnvelope<ReportingDataset>>(
    "/api/v1/budget",
    { params },
  );

  return response.data.data;
}
