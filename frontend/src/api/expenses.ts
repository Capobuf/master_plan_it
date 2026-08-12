import {
  apiClient,
  type DataEnvelope,
  type PaginatedData,
} from "./client";
import type { OperationalRevision, RevisionComparison } from "./revisions";
import type { EconomicMeasure, ProjectionTotals, PlafondMeasures } from "./projection";
import { listPlafonds } from "./plafonds";

export type ExpenseMoney = EconomicMeasure;

export interface ExpenseRegisterItem {
  id: number;
  planning_year_id: number;
  economic_year_label: number;
  cost_center_id: number;
  cost_center_name: string | null;
  kind: string;
  title: string;
  project_id: number | null;
  project_title: string | null;
  project_current: boolean;
  contract_id: number | null;
  contract_title: string | null;
  contract_current: boolean;
  vendor_count: number;
  vendor_summary: string;
  row_count: number;
  lock_version: number;
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
}

export interface ExpenseYearOption {
  id: number;
  label: number;
  active: boolean;
}

export interface ExpenseRegisterResponse extends PaginatedData<ExpenseRegisterItem> {
  currency?: string;
  basis?: "net" | "gross";
  totals: ProjectionTotals;
  year_options?: ExpenseYearOption[];
  column_preferences: ExpenseColumnPreference[];
}

export interface ExpenseListParams {
  planning_year_id?: number;
  kind?: string;
  q?: string;
  cost_center_id?: number;
  project_id?: number;
  contract_id?: number;
  vendor_id?: number;
  page?: number;
  per_page?: number;
}

export type ExpenseColumnKey =
  | "kind"
  | "contract"
  | "project"
  | "cost_center"
  | "vendor"
  | "net"
  | "vat"
  | "gross";

export interface ExpenseColumnPreference {
  key: ExpenseColumnKey;
  visible: boolean;
}

export interface ExpenseRow {
  id: number;
  position: number;
  vendor_id: number | null;
  vendor_name?: string | null;
  type: string;
  is_current_planning: boolean;
  description: string;
  notes?: string | null;
  quantity: string | null;
  unit_price: string | null;
  entered_amount: string | null;
  amount_includes_vat: boolean;
  vat_rate: string | null;
  spend_date: string | null;
  external_reference: string | null;
  lock_version: number;
  is_system_managed: boolean;
  generated: boolean;
  contract_term_id: number | null;
  amount: EconomicMeasure;
  funded_plafond: FundedPlafondReference | null;
}

export interface FundedPlafondReference {
  id: number;
  title: string;
  cost_center: { id: number; name: string };
  currency: string;
  basis: "net" | "gross";
  measures: PlafondMeasures;
}

export interface ExpenseDetail {
  id: number;
  planning_year_id: number;
  economic_year_label: number;
  cost_center_id: number;
  cost_center_name: string | null;
  kind: string;
  title: string;
  notes: string | null;
  project_id: number | null;
  project_title: string | null;
  contract_id: number | null;
  contract_title: string | null;
  current_planning_row_id: number | null;
  warnings: string[];
  lock_version: number;
  rows: ExpenseRow[];
  revision_activity: ExpenseRevision[];
  currency: string;
  basis: "net" | "gross";
  totals: ProjectionTotals;
  budget_context: { state: "preparation" | "approved" | "closed"; read_only: boolean };
}

export type ExpenseRevision = OperationalRevision;

export interface ExpenseRowInput {
  id?: number;
  position: number;
  vendor_id?: number;
  type: string;
  is_current_planning?: boolean;
  description: string;
  notes?: string;
  quantity?: string;
  unit_price?: string;
  /** Direct entry is XOR with the complete quantity/unit_price pair. */
  entered_amount?: string;
  amount_includes_vat: boolean;
  vat_rate?: string;
  spend_date?: string;
  external_reference?: string;
  lock_version?: number;
  funded_plafond_expense_id?: number | null;
}

export interface ExpenseWrite {
  planning_year_id: number;
  cost_center_id: number;
  kind: string;
  title: string;
  notes?: string;
  project_id?: number;
  contract_id?: number;
  rows: ExpenseRowInput[];
}

export interface ExpenseUpdate extends ExpenseWrite {
  lock_version: number;
  deleted_rows?: Array<{ id: number; lock_version: number }>;
}

export interface ExpenseLookupOption {
  id: number;
  name: string;
  active?: boolean;
}

export interface ExpenseContractOption {
  id: number;
  title: string;
  active?: boolean;
}

export interface PlafondExpenseOption {
  id: number;
  title: string;
  cost_center: { id: number; name: string };
  currency: string;
  basis: "net" | "gross";
  measures: PlafondMeasures;
}

export interface DeleteExpenseRequest {
  lock_version: number;
}

export interface DeleteGeneratedExpenseRequest {
  lock_version: number;
  allow_regeneration: boolean;
}

export async function listExpenses(
  params: ExpenseListParams = {},
): Promise<ExpenseRegisterResponse> {
  const { planning_year_id, ...rest } = params;
  const response = await apiClient.get<ExpenseRegisterResponse>(
    "/api/v1/expenses",
    { params: { ...rest, ...(planning_year_id ? { planning_year_id } : {}) } },
  );

  return response.data;
}

export async function getExpense(expenseId: number, planningYearId: number): Promise<ExpenseDetail> {
  const response = await apiClient.get<DataEnvelope<ExpenseDetail>>(
    `/api/v1/expenses/${expenseId}`,
    { params: { planning_year_id: planningYearId } },
  );

  return response.data.data;
}

export async function updateExpenseRegisterPreferences(
  columns: ExpenseColumnPreference[],
): Promise<ExpenseColumnPreference[]> {
  const response = await apiClient.put<DataEnvelope<{ columns: ExpenseColumnPreference[] }>>(
    "/api/v1/expenses/register-preferences",
    { columns },
  );
  return response.data.data.columns;
}

interface ExpenseBulkBase {
  planning_year_id: number;
  items: Array<{ id: number; lock_version: number }>;
}

export type ExpenseBulkRequest =
  ExpenseBulkBase & { action: "delete"; allow_regeneration: boolean };

export interface ExpenseBulkResponse {
  action: ExpenseBulkRequest["action"];
  affected_count: number;
  destinations: Array<{
    origin_expense_id: number;
    destination_expense_id: number;
    planning_year_id: number;
  }>;
}

export async function bulkExpenseAction(input: ExpenseBulkRequest): Promise<ExpenseBulkResponse> {
  const response = await apiClient.post<DataEnvelope<ExpenseBulkResponse>>(
    "/api/v1/expenses/bulk-actions",
    input,
  );
  return response.data.data;
}

export async function listExpenseVendors(): Promise<ExpenseLookupOption[]> {
  const response = await apiClient.get<PaginatedData<ExpenseLookupOption>>(
    "/api/v1/vendors",
    { params: { active: true, page: 1, per_page: 100 } },
  );

  return response.data.data;
}

interface ExpenseCostCenterOption extends ExpenseLookupOption {
  children?: ExpenseCostCenterOption[];
}

function flattenCostCenters(
  options: ExpenseCostCenterOption[],
): ExpenseLookupOption[] {
  return options.flatMap((option) => [
    { id: option.id, name: option.name, active: option.active },
    ...flattenCostCenters(option.children ?? []),
  ]);
}

export async function listExpenseCostCenters(): Promise<ExpenseLookupOption[]> {
  const response = await apiClient.get<PaginatedData<ExpenseCostCenterOption>>(
    "/api/v1/cost-centers/tree",
    { params: { active: true } },
  );

  return flattenCostCenters(response.data.data);
}

export async function listExpensePlanningYears(): Promise<ExpenseYearOption[]> {
  const response = await apiClient.get<PaginatedData<{
    id: number;
    year_label: number;
    active: boolean;
  }>>("/api/v1/planning-years", { params: { page: 1, per_page: 100 } });

  return response.data.data.map((year) => ({
    id: year.id,
    label: year.year_label,
    active: year.active,
  }));
}

export async function listExpenseContracts(): Promise<ExpenseContractOption[]> {
  const response = await apiClient.get<PaginatedData<ExpenseContractOption>>(
    "/api/v1/contracts",
    { params: { page: 1, per_page: 100 } },
  );

  return response.data.data;
}

export async function listEligiblePlafondExpenses(
  planningYearId: number,
): Promise<PlafondExpenseOption[]> {
  const response = await listPlafonds({ planning_year_id: planningYearId, per_page: 100 });
  return response.data.map((plafond) => ({
    id: plafond.id,
    title: plafond.title,
    cost_center: plafond.cost_center,
    currency: plafond.currency,
    basis: plafond.basis,
    measures: plafond.measures,
  }));
}

export async function createExpense(input: ExpenseWrite): Promise<ExpenseDetail> {
  const response = await apiClient.post<DataEnvelope<ExpenseDetail>>(
    "/api/v1/expenses",
    input,
  );

  return response.data.data;
}

export async function updateExpense(
  expenseId: number,
  input: ExpenseUpdate,
): Promise<ExpenseDetail> {
  const response = await apiClient.put<DataEnvelope<ExpenseDetail>>(
    `/api/v1/expenses/${expenseId}`,
    input,
  );

  return response.data.data;
}

export async function deleteExpense(
  expenseId: number,
  request: DeleteExpenseRequest,
): Promise<void> {
  await apiClient.delete(`/api/v1/expenses/${expenseId}`, { data: request });
}

export async function deleteGeneratedExpense(
  contractId: number,
  expenseId: number,
  request: DeleteGeneratedExpenseRequest,
): Promise<void> {
  await apiClient.delete(
    `/api/v1/contracts/${contractId}/generated-expenses/${expenseId}`,
    { data: request },
  );
}

export async function getExpenseHistory(expenseId: number, planningYearId: number): Promise<PaginatedData<ExpenseRevision>> {
  const response = await apiClient.get<PaginatedData<ExpenseRevision>>(`/api/v1/expenses/${expenseId}/history`, { params: { planning_year_id: planningYearId, per_page: 10 } });
  return response.data;
}

export async function getExpenseRevision(expenseId: number, revisionId: number, planningYearId: number): Promise<RevisionComparison> {
  const response = await apiClient.get<DataEnvelope<RevisionComparison>>(`/api/v1/expenses/${expenseId}/history/${revisionId}`, { params: { planning_year_id: planningYearId } });
  return response.data.data;
}

export async function restoreExpenseRevision(expenseId: number, revisionId: number, lockVersion: number): Promise<ExpenseDetail> {
  const response = await apiClient.post<DataEnvelope<ExpenseDetail>>(`/api/v1/expenses/${expenseId}/history/${revisionId}/restore`, { lock_version: lockVersion });
  return response.data.data;
}
