import { apiClient, type DataEnvelope } from "./client";
import type { EconomicMeasure } from "./projection";

export type BudgetBasis = "net" | "gross";
export type BudgetState = "preparation" | "approved" | "closed";

export interface PlanningYearBudget {
  id: number;
  year_label: number;
  state: BudgetState;
  lock_version: number;
}

export interface BudgetCompositionEvidence {
  schema_version: string;
  fingerprint: string;
  versions: {
    budget_lock_version: number;
    projection_version: string;
  };
  contributor_count: number;
}

export interface BudgetDrillDown {
  authorized: boolean;
  href: string | null;
}

export interface BudgetReference {
  id: number;
  title: string;
}

export interface BudgetNamedReference {
  id: number;
  name: string;
}

export interface ApprovalContributor {
  source_identity: string;
  kind: "ordinary_current_planning" | "plafond_allocation";
  expense: BudgetReference;
  row: {
    id: number;
    type: "estimate" | "quote";
    description: string;
  } | null;
  plafond: BudgetReference | null;
  dimensions: {
    cost_center: BudgetNamedReference;
    vendor: BudgetNamedReference | null;
    project: BudgetReference | null;
    contract: BudgetReference | null;
  };
  amount: EconomicMeasure;
  source_lock_version: number;
  drill_down: BudgetDrillDown;
}

interface ApprovalExclusionBase {
  source_identity: string;
  amount: EconomicMeasure;
  drill_down: BudgetDrillDown;
}

export interface IdentifiedApprovalExclusion extends ApprovalExclusionBase {
  reason: "alternative_planning" | "actual_not_proposed" | "covered_by_plafond" | "non_current_planning";
  expense: BudgetReference;
  row: {
    id: number;
    type: "estimate" | "quote" | "actual";
    description: string;
  };
  detail: string;
}

/** Soft-deleted sources are intentionally explained without reviving identifying source data. */
export interface SoftDeletedApprovalExclusion extends ApprovalExclusionBase {
  reason: "soft_deleted";
  expense: null;
  row: null;
  detail: null;
}

export type ApprovalExclusion = IdentifiedApprovalExclusion | SoftDeletedApprovalExclusion;

export interface BudgetProposal {
  composition: BudgetCompositionEvidence;
  total: EconomicMeasure;
}

export interface BudgetApprovalPreview extends BudgetProposal {
  planning_year: PlanningYearBudget;
  currency: string;
  basis: BudgetBasis;
  /** Server-calculated current calendar day in the Tenant timezone. Never derive this from browser time. */
  effective_date_max: string;
  /** Server-authored digest of the complete evidence shared with the overview. */
  surface_fingerprint: string;
  contributors: ApprovalContributor[];
  exclusions: ApprovalExclusion[];
  can_approve: boolean;
  empty_composition: boolean;
}

export interface ActiveApprovalSummary {
  id: number;
  status: "active";
  effective_date: string;
  recorded_at: string;
  total: EconomicMeasure;
}

export interface BudgetApprovalSummary extends ActiveApprovalSummary {
  planning_year_id: number;
  currency: string;
  basis: BudgetBasis;
  approved_by: {
    id: number;
    name: string;
  };
  note: string | null;
}

export interface ApproveBudgetProposalInput {
  effective_date: string;
  note: string | null;
  composition: Pick<BudgetCompositionEvidence, "schema_version" | "fingerprint" | "versions">;
}

export interface ApproveBudgetProposalResponse {
  approval: BudgetApprovalSummary;
  budget: {
    planning_year_id: number;
    state: "approved";
    lock_version: number;
  };
  economic_base: {
    basis: BudgetBasis;
    locked_at: string | null;
  };
}

/** The current overview is server-authored; no client-side approval totals are derived. */
export interface AnnualBudget {
  planning_year: PlanningYearBudget;
  currency: string;
  basis: BudgetBasis;
  /** Server-authored digest of the complete evidence shared with the preview. */
  surface_fingerprint: string;
  economic_base: {
    basis: BudgetBasis;
    locked_at: string | null;
  };
  proposal: BudgetProposal;
  approved_snapshot: ActiveApprovalSummary | null;
  informative_evaluations: EconomicMeasure;
  actuals: EconomicMeasure;
  actions: {
    can_view_approval_preview: boolean;
    can_approve: boolean;
    can_annul_active_approval: boolean;
  };
}

export interface BudgetQuery {
  planning_year_id: number;
  as_of?: string;
}

export async function getBudget(params: BudgetQuery): Promise<AnnualBudget> {
  const response = await apiClient.get<DataEnvelope<AnnualBudget>>("/api/v1/budget", { params });
  return response.data.data;
}

export async function getBudgetApprovalPreview(planningYearId: number): Promise<BudgetApprovalPreview> {
  const response = await apiClient.get<DataEnvelope<BudgetApprovalPreview>>(`/api/v1/budget/${planningYearId}/approval-preview`);
  return response.data.data;
}

/** The server rebuilds the automatic proposal; callers can send no item, amount, base, or actor fallback. */
export async function approveBudgetProposal(
  planningYearId: number,
  input: ApproveBudgetProposalInput,
): Promise<ApproveBudgetProposalResponse> {
  const response = await apiClient.post<DataEnvelope<ApproveBudgetProposalResponse>>(
    `/api/v1/budget/${planningYearId}/approve`,
    input,
  );
  return response.data.data;
}
