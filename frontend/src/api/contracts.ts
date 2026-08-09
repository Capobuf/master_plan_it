import {
  apiClient,
  type DataEnvelope,
  type PaginatedData,
} from "./client";

export interface ContractParty {
  id: number;
  name: string;
}

export interface ContractTerm {
  id: number | null;
  local_key: string;
  effective_start: string;
  effective_end: string;
  billing_cycle: "monthly" | "annual";
  quantity: string | null;
  unit_price: string | null;
  entered_amount: string;
  amount_includes_vat: boolean;
  vat_rate: string;
  auto_renew: boolean;
  net: string;
  vat: string;
  gross: string;
  currency: string | null;
  official_basis: string | null;
  lock_version: number;
}

export interface ContractOccurrence {
  term_id: number;
  planning_year: number;
  occurrence_date: string;
  source_key: string;
  net: string;
  vat: string;
  gross: string;
  currency: string | null;
  official_basis: string | null;
  suppressed: boolean;
  expense_id: number | null;
  generation_state: string | null;
}

export interface GeneratedExpense {
  id: number;
  title: string;
  confirmation_state: string | null;
  is_system_managed: boolean;
  source_key: string;
  contract_term_id: number | null;
  occurrence_date: string | null;
  net: string;
  vat: string;
  gross: string;
  currency: string | null;
  official_basis: string | null;
  lock_version: number;
}

export interface ContractRevision {
  id: number;
  operation: string;
  actor: string | null;
  timestamp: string | null;
  summary: string | null;
}

export interface Contract {
  id: number;
  vendor_id: number;
  vendor: ContractParty | null;
  cost_center_id: number;
  cost_center: ContractParty | null;
  title: string;
  description: string | null;
  active: boolean;
  renewal_date: string | null;
  renewal_notice_days: number | null;
  renewal_notes: string | null;
  currency: string | null;
  official_basis: string | null;
  term_count: number;
  generated_expense_count: number;
  lock_version: number;
  terms: ContractTerm[];
  occurrences: ContractOccurrence[];
  generated_expenses: GeneratedExpense[];
  revision_activity: ContractRevision[];
}

export interface ContractTermInput {
  id?: number;
  local_key: string;
  effective_start: string;
  effective_end: string;
  billing_cycle: "monthly" | "annual";
  quantity?: string | null;
  unit_price?: string | null;
  entered_amount: string;
  amount_includes_vat: boolean;
  vat_rate?: string | null;
  auto_renew: boolean;
  lock_version?: number;
}

export interface ContractWrite {
  vendor_id: number;
  cost_center_id: number;
  title: string;
  description?: string;
  active: boolean;
  renewal_date?: string | null;
  renewal_notice_days?: number | null;
  renewal_notes?: string;
  terms: ContractTermInput[];
}

export interface ContractUpdate extends ContractWrite {
  lock_version: number;
}

export interface ContractListParams {
  page?: number;
  per_page?: number;
}

export interface DeleteRequest {
  lock_version: number;
  deletion_reason?: string;
}

export interface GeneratedExpenseDeleteRequest {
  lock_version: number;
  allow_regeneration: boolean;
}

export interface SuppressRequest {
  reason?: string;
}

export interface ContractLookupOption {
  id: number;
  name: string;
  active?: boolean;
}

export async function listContractVendors(): Promise<PaginatedData<ContractLookupOption>> {
  const response = await apiClient.get<PaginatedData<ContractLookupOption>>(
    "/api/v1/vendors",
    { params: { per_page: 100 } },
  );
  return response.data;
}

export async function listContractCostCenters(): Promise<PaginatedData<ContractLookupOption>> {
  const response = await apiClient.get<PaginatedData<ContractLookupOption>>(
    "/api/v1/cost-centers",
    { params: { per_page: 100 } },
  );
  return response.data;
}

export async function listContracts(
  params: ContractListParams = {},
): Promise<PaginatedData<Contract>> {
  const response = await apiClient.get<PaginatedData<Contract>>(
    "/api/v1/contracts",
    { params },
  );
  return response.data;
}

export async function getContract(contractId: number): Promise<Contract> {
  const response = await apiClient.get<DataEnvelope<Contract>>(
    `/api/v1/contracts/${contractId}`,
  );
  return response.data.data;
}

export async function createContract(input: ContractWrite): Promise<Contract> {
  const response = await apiClient.post<DataEnvelope<Contract>>(
    "/api/v1/contracts",
    input,
  );
  return response.data.data;
}

export async function updateContract(
  contractId: number,
  input: ContractUpdate,
): Promise<Contract> {
  const response = await apiClient.put<DataEnvelope<Contract>>(
    `/api/v1/contracts/${contractId}`,
    input,
  );
  return response.data.data;
}

export async function deleteContract(
  contractId: number,
  input: DeleteRequest,
): Promise<void> {
  await apiClient.delete(`/api/v1/contracts/${contractId}`, { data: input });
}

export async function deleteContractTerm(
  contractId: number,
  termId: number,
  input: DeleteRequest,
): Promise<void> {
  await apiClient.delete(`/api/v1/contracts/${contractId}/terms/${termId}`, {
    data: input,
  });
}

export async function synchronizeContract(contractId: number): Promise<Record<string, unknown>> {
  const response = await apiClient.post<DataEnvelope<Record<string, unknown>>>(
    `/api/v1/contracts/${contractId}/synchronize`,
  );
  return response.data.data;
}

export async function generateContractOccurrence(
  contractId: number,
  year: number,
): Promise<GeneratedExpense> {
  const response = await apiClient.post<DataEnvelope<GeneratedExpense>>(
    `/api/v1/contracts/${contractId}/generate/${year}`,
  );
  return response.data.data;
}

export async function suppressContractOccurrence(
  contractId: number,
  sourceKey: string,
  input: SuppressRequest = {},
): Promise<void> {
  await apiClient.post(
    `/api/v1/contracts/${contractId}/occurrences/${sourceKey}/suppress`,
    input,
  );
}

export async function resumeContractOccurrence(
  contractId: number,
  sourceKey: string,
): Promise<void> {
  await apiClient.post(
    `/api/v1/contracts/${contractId}/occurrences/${sourceKey}/resume`,
  );
}

export async function resumeAndGenerateContractOccurrence(
  contractId: number,
  sourceKey: string,
): Promise<GeneratedExpense> {
  const response = await apiClient.post<DataEnvelope<GeneratedExpense>>(
    `/api/v1/contracts/${contractId}/occurrences/${sourceKey}/resume-and-generate`,
  );
  return response.data.data;
}

export async function getContractHistory(
  contractId: number,
  params: Pick<ContractListParams, "page" | "per_page"> = {},
): Promise<PaginatedData<ContractRevision>> {
  const response = await apiClient.get<PaginatedData<ContractRevision>>(
    `/api/v1/contracts/${contractId}/history`,
    { params },
  );
  return response.data;
}

export async function deleteGeneratedExpense(
  contractId: number,
  expenseId: number,
  input: GeneratedExpenseDeleteRequest,
): Promise<void> {
  await apiClient.delete(
    `/api/v1/contracts/${contractId}/generated-expenses/${expenseId}`,
    { data: input },
  );
}
