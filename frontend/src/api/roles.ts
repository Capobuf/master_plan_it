import { apiClient, type DataEnvelope, type PaginatedData } from "./client";
export interface Role { id: number; name: string; abilities: string[]; }
export interface Ability { name: string; label: string; }
export async function listRoles(): Promise<PaginatedData<Role>> { return (await apiClient.get<PaginatedData<Role>>("/api/v1/roles", { params: { per_page: 100 } })).data; }
export async function listAbilities(): Promise<PaginatedData<Ability>> { return (await apiClient.get<PaginatedData<Ability>>("/api/v1/abilities", { params: { per_page: 100 } })).data; }
export async function createRole(name: string, abilities: string[]): Promise<Role> { return (await apiClient.post<DataEnvelope<Role>>("/api/v1/roles", { name, abilities })).data.data; }
export async function updateRole(id: number, name: string, abilities: string[]): Promise<Role> { return (await apiClient.put<DataEnvelope<Role>>(`/api/v1/roles/${id}`, { name, abilities })).data.data; }
export async function deleteRole(id: number): Promise<void> { await apiClient.delete(`/api/v1/roles/${id}`); }
