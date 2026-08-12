import { apiClient, type DataEnvelope } from "./client";
import type { PlafondMeasures, ProjectionTotals } from "./projection";

export interface AnnualBudgetExpense {
  id: number;
  title: string;
  kind: "ordinary" | "plafond";
  cost_center_id: number;
  cost_center_name: string;
  project_id: number | null;
  project_title: string | null;
  contract_id: number | null;
  contract_title: string | null;
  vendor_id: number | null;
  vendor_name: string | null;
  current_planning_row_id: number | null;
  funded_plafond_expense_id: number | null;
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
  plafond_measures?: PlafondMeasures;
  planned?: string | null;
  approved?: string | null;
  approved_basis?: "net" | "gross" | null;
  actual?: string;
  residual?: string | null;
  variance?: string | null;
  has_actual?: boolean;
  lock_version?: number;
  rows?: Array<Record<string, unknown>>;
}

export interface AnnualBudgetSummary {
  /** Approval fields remain migration-only until Slice 025. */
  initial_approved: string;
  approved_variations: string;
  approved_current: string;
  proposed?: string;
  actual?: string;
  residual?: string;
  variance?: string;
  utilization_percentage?: string | null;
  currency?: string;
  official_basis?: "net" | "gross";
  unapproved_actual_expenses?: number;
}

export interface AnnualBudget {
  mode: "current" | "historical";
  requested_as_of: string | null;
  cutoff_utc: string | null;
  read_only: boolean;
  currency?: string;
  basis?: "net" | "gross";
  totals?: ProjectionTotals;
  budget: {
    planning_year_id: number;
    year: number;
    state: "preparation" | "approved" | "closed";
    lock_version: number;
    warning: "BUDGET_CLOSED" | null;
    history_activated_at: string | null;
  };
  summary: AnnualBudgetSummary;
  expenses: AnnualBudgetExpense[];
  historical_context?: {
    approval_operations: Array<Record<string, unknown>>;
    cost_centers: Array<Record<string, unknown>>;
    projects: Array<Record<string, unknown>>;
    contracts: Array<Record<string, unknown>>;
    contract_terms: Array<Record<string, unknown>>;
    vendors: Array<Record<string, unknown>>;
  };
}

export interface BudgetQuery {
  planning_year_id?: number;
  cost_center_id?: number;
  as_of?: string;
}

export interface ApprovalDecisionInput {
  budget_lock_version: number;
  effective_date: string;
  reason?: string;
  items: Array<{ expense_id: number; expense_lock_version: number; approved_amount: string }>;
}

export async function getBudget(params: BudgetQuery = {}): Promise<AnnualBudget> {
  const response = await apiClient.get<DataEnvelope<AnnualBudget>>("/api/v1/budget", { params });
  return response.data.data;
}

export async function applyBudgetApproval(planningYearId: number, input: ApprovalDecisionInput): Promise<AnnualBudget> {
  const response = await apiClient.post<DataEnvelope<AnnualBudget>>(`/api/v1/budget/${planningYearId}/approval-decisions`, input);
  return response.data.data;
}

export async function closeBudget(planningYearId: number, lockVersion: number): Promise<AnnualBudget> {
  const response = await apiClient.post<DataEnvelope<AnnualBudget>>(`/api/v1/budget/${planningYearId}/close`, { lock_version: lockVersion });
  return response.data.data;
}
