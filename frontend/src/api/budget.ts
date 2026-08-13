import { ApiError, apiClient, type DataEnvelope, type PaginatedData, type PaginationParams } from "./client";
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
  planning_year: Pick<PlanningYearBudget, "id" | "year_label">;
  currency: string;
  basis: BudgetBasis;
  effective_date: string;
  recorded_at: string;
  total: EconomicMeasure;
}

export type BudgetApprovalStatus = "active" | "annulled";

export interface BudgetApprovalActor {
  id: number;
  name: string;
}

export interface BudgetApprovalSummary {
  id: number;
  status: BudgetApprovalStatus;
  planning_year_id: number;
  currency: string;
  basis: BudgetBasis;
  total: EconomicMeasure;
  effective_date: string;
  recorded_at: string;
  approved_by: BudgetApprovalActor;
  note: string | null;
  annulled_at?: string | null;
  annulled_by?: BudgetApprovalActor | null;
  annulment_note?: string | null;
}

export interface BudgetApprovalDetail {
  id: number;
  status: BudgetApprovalStatus;
  planning_year: Pick<PlanningYearBudget, "id" | "year_label">;
  currency: string;
  basis: BudgetBasis;
  total: EconomicMeasure;
  effective_date: string;
  recorded_at: string;
  approved_by: BudgetApprovalActor;
  note: string | null;
  composition: Pick<BudgetCompositionEvidence, "schema_version" | "fingerprint" | "contributor_count">;
  contributors: ApprovalContributor[];
  annulment: {
    annulled_at: string;
    annulled_by: BudgetApprovalActor;
    note: string | null;
  } | null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function hasApprovalStatus(value: unknown): value is BudgetApprovalStatus {
  return value === "active" || value === "annulled";
}

const canonicalMoney = /^(?:0|[1-9]\d*|-[1-9]\d*)\.\d{2}$/;
const calendarDate = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;
const timestamp = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/;
const isPositiveId = (value: unknown): value is number => typeof value === "number" && Number.isSafeInteger(value) && value > 0;
const isText = (value: unknown): value is string => typeof value === "string";
const isBasis = (value: unknown): value is BudgetBasis => value === "net" || value === "gross";

function isCalendarDate(value: unknown): value is string {
  if (!isText(value) || !calendarDate.test(value)) return false;
  const [year, month, day] = value.split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
}

function isTimestamp(value: unknown): value is string {
  return isText(value) && timestamp.test(value) && isCalendarDate(value.slice(0, 10)) && Number.isFinite(Date.parse(value));
}

function isDrillDown(value: unknown): value is BudgetDrillDown {
  if (!isRecord(value) || typeof value.authorized !== "boolean" || !(value.href === null || isText(value.href))) return false;
  return value.href === null || /^\/api\/v1\/(expenses|plafonds)\/[1-9]\d*$/.test(value.href);
}

function isActor(value: unknown): value is BudgetApprovalActor {
  return isRecord(value) && isPositiveId(value.id) && isText(value.name) && value.name.length > 0;
}

function isMeasure(value: unknown): value is EconomicMeasure {
  return isRecord(value) && ["net", "vat", "gross", "official"].every((key) => isText(value[key]) && canonicalMoney.test(value[key]));
}

function cents(value: string): bigint {
  const negative = value.startsWith("-");
  const [integer, fraction] = (negative ? value.slice(1) : value).split(".");
  const amount = BigInt(`${integer}${fraction}`);
  return negative ? -amount : amount;
}

function hasEconomicRelations(value: unknown, basis: BudgetBasis): value is EconomicMeasure {
  return isMeasure(value) && cents(value.net) + cents(value.vat) === cents(value.gross) && value.official === value[basis];
}

function isReference(value: unknown, field: "name" | "title"): boolean {
  return isRecord(value) && isPositiveId(value.id) && isText(value[field]) && value[field].length > 0;
}

function isContributor(value: unknown, basis: BudgetBasis): value is ApprovalContributor {
  if (!isRecord(value) || !isText(value.source_identity) || value.source_identity.length === 0
    || (value.kind !== "ordinary_current_planning" && value.kind !== "plafond_allocation")
    || !isReference(value.expense, "title") || !hasEconomicRelations(value.amount, basis) || !isPositiveId(value.source_lock_version) || !isDrillDown(value.drill_down)) return false;
  const row = value.row;
  if (!(row === null || (isRecord(row) && isPositiveId(row.id) && (row.type === "estimate" || row.type === "quote") && isText(row.description) && row.description.length > 0))) return false;
  if (!(value.plafond === null || isReference(value.plafond, "title"))) return false;
  if ((value.kind === "ordinary_current_planning" && (row === null || value.plafond !== null))
    || (value.kind === "plafond_allocation" && (row !== null || value.plafond === null))) return false;
  const dimensions = value.dimensions;
  return isRecord(dimensions) && isReference(dimensions.cost_center, "name")
    && (dimensions.vendor === null || isReference(dimensions.vendor, "name"))
    && (dimensions.project === null || isReference(dimensions.project, "title"))
    && (dimensions.contract === null || isReference(dimensions.contract, "title"));
}

function sumMeasure(contributors: ApprovalContributor[], key: keyof EconomicMeasure): string {
  const cents = contributors.reduce((total, contributor) => {
    const raw = contributor.amount[key];
    const negative = raw.startsWith("-");
    const [integer, fraction] = (negative ? raw.slice(1) : raw).split(".");
    const value = BigInt(`${integer}${fraction}`);
    return total + (negative ? -value : value);
  }, 0n);
  const negative = cents < 0n;
  const digits = (negative ? -cents : cents).toString().padStart(3, "0");
  return `${negative ? "-" : ""}${digits.slice(0, -2)}.${digits.slice(-2)}`;
}

function isSummary(value: unknown, planningYearId: number): value is BudgetApprovalSummary {
  if (!isRecord(value) || !isPositiveId(value.id) || !hasApprovalStatus(value.status) || value.planning_year_id !== planningYearId
    || !isText(value.currency) || !/^[A-Z]{3}$/.test(value.currency) || !isBasis(value.basis) || !hasEconomicRelations(value.total, value.basis)
    || !isCalendarDate(value.effective_date) || !isTimestamp(value.recorded_at)
    || !isActor(value.approved_by) || !(value.note === null || isText(value.note))) return false;
  const annulled = value.status === "annulled";
  return annulled
    ? isTimestamp(value.annulled_at) && isActor(value.annulled_by) && isText(value.annulment_note) && value.annulment_note.trim().length > 0
    : value.annulled_at === null && value.annulled_by === null && value.annulment_note === null;
}

function assertApprovalHistory(value: unknown, planningYearId: number, requestedPage?: number): asserts value is PaginatedData<BudgetApprovalSummary> {
  const meta = isRecord(value) ? value.meta : null;
  if (!isRecord(value) || !Array.isArray(value.data) || !isRecord(meta)
    || !["current_page", "last_page", "per_page"].every((key) => isPositiveId(meta[key])) || !(typeof meta.total === "number" && Number.isSafeInteger(meta.total) && meta.total >= 0)
    || (meta.current_page as number) > (meta.last_page as number) || (requestedPage !== undefined && meta.current_page !== requestedPage)
    || meta.total < value.data.length || !value.data.every((item) => isSummary(item, planningYearId))) {
    throw new Error("La cronologia approvazioni ricevuta non rispetta il contratto previsto.");
  }
}

function assertApprovalDetail(value: unknown, planningYearId: number, approvalId: number): asserts value is BudgetApprovalDetail {
  if (!isRecord(value) || value.id !== approvalId || !hasApprovalStatus(value.status) || !isRecord(value.planning_year) || value.planning_year.id !== planningYearId || !isPositiveId(value.planning_year.id) || !isPositiveId(value.planning_year.year_label)
    || !isText(value.currency) || !/^[A-Z]{3}$/.test(value.currency) || !isBasis(value.basis) || !hasEconomicRelations(value.total, value.basis)
    || !isCalendarDate(value.effective_date) || !isTimestamp(value.recorded_at) || !isActor(value.approved_by) || !(value.note === null || isText(value.note))
    || !Array.isArray(value.contributors) || !value.contributors.every((contributor) => isContributor(contributor, value.basis as BudgetBasis)) || !isRecord(value.composition)
    || !isText(value.composition.fingerprint) || value.composition.fingerprint.length === 0 || !isText(value.composition.schema_version) || value.composition.schema_version.length === 0
    || value.composition.contributor_count !== value.contributors.length || !Number.isSafeInteger(value.composition.contributor_count) || value.composition.contributor_count < 0
    || ["net", "vat", "gross", "official"].some((key) => sumMeasure(value.contributors as ApprovalContributor[], key as keyof EconomicMeasure) !== (value.total as EconomicMeasure)[key as keyof EconomicMeasure])) {
    throw new Error("La fotografia approvazione ricevuta non rispetta il contratto previsto.");
  }
  const annulment = value.annulment;
  if (!(annulment === null || (isRecord(annulment) && isTimestamp(annulment.annulled_at) && isActor(annulment.annulled_by) && isText(annulment.note) && annulment.note.trim().length > 0))
    || (value.status === "active" && annulment !== null) || (value.status === "annulled" && annulment === null)) throw new Error("La fotografia approvazione ricevuta non rispetta il contratto previsto.");
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

/** Historical decisions are immutable records; the only accepted collection parameters are server pagination. */
export async function getBudgetApprovalHistory(
  planningYearId: number,
  params: PaginationParams = {},
): Promise<PaginatedData<BudgetApprovalSummary>> {
  const response = await apiClient.get<PaginatedData<BudgetApprovalSummary>>(
    `/api/v1/budget/${planningYearId}/approvals`,
    { params },
  );
  assertApprovalHistory(response.data, planningYearId, params.page);
  return response.data;
}

/** Detail remains a stored approval snapshot and deliberately accepts no client filter or mutation input. */
export async function getBudgetApprovalDetail(
  planningYearId: number,
  approvalId: number,
): Promise<BudgetApprovalDetail> {
  const response = await apiClient.get<DataEnvelope<BudgetApprovalDetail>>(
    `/api/v1/budget/${planningYearId}/approvals/${approvalId}`,
  );
  assertApprovalDetail(response.data.data, planningYearId, approvalId);
  return response.data.data;
}

/** The server rebuilds the automatic proposal; callers can send no item, amount, base, or actor fallback. */
export async function approveBudgetProposal(
  planningYearId: number,
  input: ApproveBudgetProposalInput,
): Promise<ApproveBudgetProposalResponse> {
  try {
    const response = await apiClient.post<DataEnvelope<ApproveBudgetProposalResponse>>(
      `/api/v1/budget/${planningYearId}/approve`,
      input,
    );
    return response.data.data;
  } catch (cause: unknown) {
    throw ApiError.from(cause);
  }
}
