import { apiClient, type DataEnvelope, type PaginatedData, type PaginationParams } from "./client";
export interface CostCenter { id: number; name: string; parent_id: number | null; active: boolean; lock_version: number; depth?: number; children?: CostCenter[]; }
export interface CostCenterInput { name: string; parent_id?: number | null; }
export interface Revision { operation: string; actor: string | null; timestamp: string | null; reason: string | null; source_revision_id: number | null; restored_from_revision_id: number | null; }
export async function listCostCenters(params: PaginationParams = {}): Promise<PaginatedData<CostCenter>> { return (await apiClient.get<PaginatedData<CostCenter>>("/api/v1/cost-centers", { params })).data; }
export async function createCostCenter(input: CostCenterInput): Promise<CostCenter> { return (await apiClient.post<DataEnvelope<CostCenter>>("/api/v1/cost-centers", input)).data.data; }
export async function updateCostCenter(id: number, input: CostCenterInput & { lock_version: number }): Promise<CostCenter> { return (await apiClient.put<DataEnvelope<CostCenter>>(`/api/v1/cost-centers/${id}`, input)).data.data; }
export async function deactivateCostCenter(id: number, lock_version: number): Promise<CostCenter> { return (await apiClient.post<DataEnvelope<CostCenter>>(`/api/v1/cost-centers/${id}/deactivate`, { lock_version })).data.data; }
export async function reactivateCostCenter(id: number, lock_version: number): Promise<CostCenter> { return (await apiClient.post<DataEnvelope<CostCenter>>(`/api/v1/cost-centers/${id}/reactivate`, { lock_version })).data.data; }
export async function listCostCenterHistory(id: number, params: PaginationParams = {}): Promise<PaginatedData<Revision>> { return (await apiClient.get<PaginatedData<Revision>>(`/api/v1/cost-centers/${id}/history`, { params })).data; }
export async function restoreCostCenter(id: number, version: number, lock_version: number): Promise<CostCenter> { return (await apiClient.post<DataEnvelope<CostCenter>>(`/api/v1/cost-centers/${id}/history/${version}/restore`, { lock_version })).data.data; }
