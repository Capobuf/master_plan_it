import {
  apiClient,
  type DataEnvelope,
  type PaginatedData,
} from "./client";

export interface ExpenseMoney {
  net: string;
  vat: string;
  gross: string;
  currency: string;
  official_basis: string;
}

export interface ExpenseRegisterItem {
  id: number;
  planning_year_id: number;
  planning_year_label: number;
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
  row_count: number;
  totals: ExpenseMoney;
}

export interface ExpenseYearOption {
  id: number;
  label: number;
  active: boolean;
}

export interface ExpenseRegisterResponse extends PaginatedData<ExpenseRegisterItem> {
  totals?: ExpenseMoney;
  year_options?: ExpenseYearOption[];
}

export interface ExpenseListParams {
  planning_year_id?: number;
  kind?: string;
  page?: number;
  per_page?: number;
}

export interface ExpenseRow {
  id: number;
  position: number;
  vendor_id: number | null;
  vendor_name?: string | null;
  type: string;
  confirmation_state: string | null;
  description: string;
  quantity: string | null;
  unit_price: string | null;
  entered_amount: string;
  amount_includes_vat: boolean;
  vat_rate: string | null;
  is_extra: boolean;
  funded_plafond_expense_id: number | null;
  spend_date: string | null;
  period_start: string | null;
  period_end: string | null;
  distribution: string | null;
  external_reference: string | null;
  lock_version: number;
  is_system_managed: boolean;
  generated: boolean;
  contract_term_id: number | null;
  totals: ExpenseMoney;
}

export interface ExpenseDetail {
  id: number;
  planning_year_id: number;
  planning_year_label: number;
  cost_center_id: number;
  cost_center_name: string | null;
  kind: string;
  title: string;
  notes: string | null;
  project_id: number | null;
  project_title: string | null;
  contract_id: number | null;
  lock_version: number;
  rows: ExpenseRow[];
  totals: ExpenseMoney;
}

export interface ExpenseRowInput {
  id?: number;
  position: number;
  vendor_id?: number;
  type: string;
  description: string;
  quantity?: string;
  unit_price?: string;
  entered_amount: string;
  amount_includes_vat: boolean;
  vat_rate?: string;
  is_extra: boolean;
  funded_plafond_expense_id?: number;
  spend_date?: string;
  period_start?: string;
  period_end?: string;
  distribution?: string;
  external_reference?: string;
  lock_version?: number;
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
  cost_center_name: string | null;
}

export interface DeleteExpenseRequest {
  lock_version: number;
}

export interface DeleteGeneratedExpenseRequest {
  lock_version: number;
  allow_regeneration: boolean;
}

export interface ConfirmActualRequest {
  lock_version: number;
}

export async function listExpenses(
  params: ExpenseListParams = {},
): Promise<ExpenseRegisterResponse> {
  const { planning_year_id, ...rest } = params;
  const response = await apiClient.get<ExpenseRegisterResponse>(
    "/api/v1/expenses",
    { params: { ...rest, ...(planning_year_id ? { year: planning_year_id } : {}) } },
  );

  return response.data;
}

export async function getExpense(expenseId: number): Promise<ExpenseDetail> {
  const response = await apiClient.get<DataEnvelope<ExpenseDetail>>(
    `/api/v1/expenses/${expenseId}`,
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
  const items: PlafondExpenseOption[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await listExpenses({ planning_year_id: planningYearId, kind: "plafond", page, per_page: 100 });
    items.push(...response.data.map((expense) => ({ id: expense.id, title: expense.title, cost_center_name: expense.cost_center_name })));
    lastPage = response.meta.last_page;
    page = response.meta.current_page + 1;
  } while (page <= lastPage);
  return items;
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

export async function confirmActual(
  expenseId: number,
  rowId: number,
  request: ConfirmActualRequest,
): Promise<void> {
  await apiClient.post(
    `/api/v1/expenses/${expenseId}/rows/${rowId}/confirm`,
    request,
  );
}
