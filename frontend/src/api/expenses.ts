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
  vendor_name?: string | null;
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
  cost_center_id?: number;
  q?: string;
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
  contract_id: number | null;
  lock_version: number;
  rows: ExpenseRow[];
  totals: ExpenseMoney;
}

export interface DeleteExpenseRequest {
  lock_version: number;
  allow_regeneration?: boolean;
}

export interface ConfirmActualRequest {
  lock_version: number;
}

export async function listExpenses(
  params: ExpenseListParams = {},
): Promise<ExpenseRegisterResponse> {
  const response = await apiClient.get<ExpenseRegisterResponse>(
    "/api/v1/expenses",
    { params },
  );

  return response.data;
}

export async function getExpense(expenseId: number): Promise<ExpenseDetail> {
  const response = await apiClient.get<DataEnvelope<ExpenseDetail>>(
    `/api/v1/expenses/${expenseId}`,
  );

  return response.data.data;
}

export async function deleteExpense(
  expenseId: number,
  request: DeleteExpenseRequest,
): Promise<void> {
  await apiClient.delete(`/api/v1/expenses/${expenseId}`, { data: request });
}

export async function confirmActual(
  expenseId: number,
  rowId: number,
  request: ConfirmActualRequest,
): Promise<ExpenseRow> {
  const response = await apiClient.post<DataEnvelope<ExpenseRow>>(
    `/api/v1/expenses/${expenseId}/rows/${rowId}/confirm`,
    request,
  );

  return response.data.data;
}
