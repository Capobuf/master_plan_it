import { apiClient, type DataEnvelope, type PaginatedData, type PaginationParams } from "./client";
import type { EconomicMeasure, PlafondMeasures } from "./projection";

export interface CostCenterReference { id: number; name: string; }
export interface PlafondIdentity { id: number; title: string; }

export interface PlafondSummary {
  id: number;
  planning_year_id: number;
  economic_year_label: number;
  title: string;
  notes: string | null;
  cost_center: CostCenterReference;
  lock_version: number;
  currency: string;
  basis: "net" | "gross";
  measures: PlafondMeasures;
}

export interface AllocationAdjustment {
  id: number;
  type: "allocation_adjustment";
  position: number;
  description: string;
  notes: string | null;
  date: string;
  created_by: { id: number; name: string };
  lock_version: number;
  amount: EconomicMeasure;
}

export interface PlafondCoveredRow {
  expense_id: number;
  expense_title: string;
  row_id: number;
  description: string;
  type: "estimate" | "quote" | "actual";
  contributes_to_coverage_planned: boolean;
  contributes_to_consumed: boolean;
  date: string | null;
  expense_cost_center: CostCenterReference;
  plafond_cost_center: CostCenterReference;
  amount: EconomicMeasure;
}

export interface PlafondDetail extends PlafondSummary {
  kind: "plafond";
  budget_context: { state: "preparation" | "approved" | "closed"; read_only: boolean };
  allocation_adjustments: AllocationAdjustment[];
  covered_rows: PlafondCoveredRow[];
}

export interface PlafondImpact {
  plafond: PlafondIdentity;
  currency: string;
  basis: "net" | "gross";
  current: PlafondMeasures;
  proposed: PlafondMeasures;
  requested: string;
  shortage: string;
  can_confirm: boolean;
  blocking_rows: PlafondCoveredRow[];
}

export interface AllocationAdjustmentInput {
  description: string;
  notes?: string | null;
  entered_amount?: string;
  quantity?: string;
  unit_price?: string;
  amount_includes_vat: boolean;
  vat_rate?: string;
  date: string;
}

export interface CreatePlafondInput {
  planning_year_id: number;
  cost_center_id: number;
  title: string;
  notes?: string | null;
  initial_allocation: AllocationAdjustmentInput;
}

export interface AllocationAdjustmentRequest {
  lock_version: number;
  adjustment: AllocationAdjustmentInput;
}

export interface PlafondListParams extends PaginationParams {
  planning_year_id: number;
  cost_center_id?: number;
}

export interface PlafondListResponse extends PaginatedData<PlafondSummary> {
  currency: string;
  basis: "net" | "gross";
}

export type PlafondReportLine = PlafondCoveredRow;
export interface PlafondReportItem {
  plafond: PlafondIdentity & { cost_center: CostCenterReference };
  currency: string;
  basis: "net" | "gross";
  measures: PlafondMeasures;
  allocation_lines: AllocationAdjustment[];
  covered_lines: PlafondReportLine[];
}
export interface PlafondReportResponse {
  data: PlafondReportItem[];
  currency: string;
  basis: "net" | "gross";
  filters: { planning_year_id: number; cost_center_id: number | null };
}

export async function listPlafonds(params: PlafondListParams): Promise<PlafondListResponse> {
  const response = await apiClient.get<PlafondListResponse>("/api/v1/plafonds", { params });
  return response.data;
}

export async function getPlafond(id: number, planningYearId: number): Promise<PlafondDetail> {
  const response = await apiClient.get<DataEnvelope<PlafondDetail>>(`/api/v1/plafonds/${id}`, { params: { planning_year_id: planningYearId } });
  return response.data.data;
}

export async function createPlafond(input: CreatePlafondInput): Promise<PlafondDetail> {
  const response = await apiClient.post<DataEnvelope<PlafondDetail>>("/api/v1/plafonds", input);
  return response.data.data;
}

export async function previewAllocationAdjustment(id: number, input: AllocationAdjustmentRequest): Promise<PlafondImpact> {
  const response = await apiClient.post<DataEnvelope<PlafondImpact>>(`/api/v1/plafonds/${id}/allocation-adjustments/preview`, input);
  return response.data.data;
}

export async function addAllocationAdjustment(id: number, input: AllocationAdjustmentRequest): Promise<PlafondDetail> {
  const response = await apiClient.post<DataEnvelope<PlafondDetail>>(`/api/v1/plafonds/${id}/allocation-adjustments`, input);
  return response.data.data;
}

export async function getPlafondReport(params: Pick<PlafondListParams, "planning_year_id" | "cost_center_id">): Promise<PlafondReportResponse> {
  const response = await apiClient.get<PlafondReportResponse>("/api/v1/plafonds/report", { params });
  return response.data;
}
