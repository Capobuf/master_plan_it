import { apiClient, type DataEnvelope, type PaginatedData, type PaginationParams } from "./client";
import type { OperationalRevision } from "./revisions";

export interface Vendor { id: number; name: string; vat_number: string | null; email: string | null; phone: string | null; address: string | null; active: boolean; lock_version: number; }
export interface VendorInput { name: string; vat_number?: string | null; email?: string | null; phone?: string | null; address?: string | null; }
export type Revision = OperationalRevision;
export interface VendorListParams extends PaginationParams { q?: string; active?: boolean; }
export async function listVendors(params: VendorListParams = {}): Promise<PaginatedData<Vendor>> { return (await apiClient.get<PaginatedData<Vendor>>("/api/v1/vendors", { params })).data; }
export async function createVendor(input: VendorInput): Promise<Vendor> { return (await apiClient.post<DataEnvelope<Vendor>>("/api/v1/vendors", input)).data.data; }
export async function updateVendor(id: number, input: VendorInput & { lock_version: number }): Promise<Vendor> { return (await apiClient.put<DataEnvelope<Vendor>>(`/api/v1/vendors/${id}`, input)).data.data; }
export async function deactivateVendor(id: number, lock_version: number): Promise<Vendor> { return (await apiClient.post<DataEnvelope<Vendor>>(`/api/v1/vendors/${id}/deactivate`, { lock_version })).data.data; }
export async function reactivateVendor(id: number, lock_version: number): Promise<Vendor> { return (await apiClient.post<DataEnvelope<Vendor>>(`/api/v1/vendors/${id}/reactivate`, { lock_version })).data.data; }
export async function deleteVendor(id: number, lock_version: number): Promise<void> { await apiClient.delete(`/api/v1/vendors/${id}`, { data: { lock_version } }); }
export async function listVendorHistory(id: number, params: PaginationParams = {}): Promise<PaginatedData<Revision>> { return (await apiClient.get<PaginatedData<Revision>>(`/api/v1/vendors/${id}/history`, { params })).data; }
export async function restoreVendor(id: number, revisionId: number, lock_version: number): Promise<Vendor> { return (await apiClient.post<DataEnvelope<Vendor>>(`/api/v1/vendors/${id}/history/${revisionId}/restore`, { lock_version })).data.data; }
