import { apiClient, type DataEnvelope, type PaginatedData, type PaginationParams, type Tenant } from "./client";
export interface TenantCreateInput { name: string; code: string; currency_code: string; language_code: string; timezone: string; default_vat_rate: string; }
export interface TenantUpdateInput { code: string; currency_code: string; language_code: string; lock_version: number; }
export async function listTenants(params: PaginationParams = {}): Promise<PaginatedData<Tenant>> { return (await apiClient.get<PaginatedData<Tenant>>("/api/v1/tenants", { params })).data; }
export async function createTenant(input: TenantCreateInput): Promise<Tenant> { return (await apiClient.post<DataEnvelope<Tenant>>("/api/v1/tenants", input)).data.data; }
export async function updateTenant(id: number, input: TenantUpdateInput): Promise<Tenant> { return (await apiClient.put<DataEnvelope<Tenant>>(`/api/v1/tenants/${id}`, input)).data.data; }
export async function deactivateTenant(id: number, lock_version: number, confirmation_code: string): Promise<Tenant> { return (await apiClient.post<DataEnvelope<Tenant>>(`/api/v1/tenants/${id}/deactivate`, { lock_version, confirmation_code })).data.data; }
export async function reactivateTenant(id: number, lock_version: number): Promise<Tenant> { return (await apiClient.post<DataEnvelope<Tenant>>(`/api/v1/tenants/${id}/reactivate`, { lock_version })).data.data; }
