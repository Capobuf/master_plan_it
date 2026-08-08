import { apiClient, type DataEnvelope, type PaginatedData, type PaginationParams } from "./client";
export interface TenantUser { id: number; name: string; email: string; active: boolean; lock_version: number; roles: { id: number; name: string }[]; role_ids: number[]; }
export interface UserInput { name: string; email: string; }
export interface UserCreate extends UserInput { password: string; roles: number[]; }
export interface UserListParams extends PaginationParams { q?: string; }
export async function listUsers(params: UserListParams = {}): Promise<PaginatedData<TenantUser>> { return (await apiClient.get<PaginatedData<TenantUser>>("/api/v1/users", { params })).data; }
export async function createUser(input: UserCreate): Promise<TenantUser> { return (await apiClient.post<DataEnvelope<TenantUser>>("/api/v1/users", input)).data.data; }
export async function updateUser(id: number, input: UserInput): Promise<TenantUser> { return (await apiClient.put<DataEnvelope<TenantUser>>(`/api/v1/users/${id}`, input)).data.data; }
export async function assignUserRoles(id: number, roles: number[]): Promise<TenantUser> { return (await apiClient.put<DataEnvelope<TenantUser>>(`/api/v1/users/${id}/roles`, { roles })).data.data; }
export async function deactivateUser(id: number): Promise<TenantUser> { return (await apiClient.post<DataEnvelope<TenantUser>>(`/api/v1/users/${id}/deactivate`)).data.data; }
export async function resetUserPassword(id: number, password: string, password_confirmation: string): Promise<void> { await apiClient.put(`/api/v1/users/${id}/password`, { password, password_confirmation }); }
