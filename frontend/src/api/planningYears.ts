import { apiClient, type DataEnvelope, type PaginatedData } from "./client";
export interface PlanningYear { id: number; year_label: number; start_date: string; end_date: string; active: boolean; lock_version: number; }
export async function listPlanningYears(): Promise<PaginatedData<PlanningYear>> { return (await apiClient.get<PaginatedData<PlanningYear>>("/api/v1/planning-years", { params: { per_page: 100 } })).data; }
export async function createPlanningYear(year_label: number): Promise<PlanningYear> { return (await apiClient.post<DataEnvelope<PlanningYear>>("/api/v1/planning-years", { year_label })).data.data; }
export async function deactivatePlanningYear(id: number, lock_version: number): Promise<PlanningYear> { return (await apiClient.post<DataEnvelope<PlanningYear>>(`/api/v1/planning-years/${id}/deactivate`, { lock_version })).data.data; }
export async function reactivatePlanningYear(id: number, lock_version: number): Promise<PlanningYear> { return (await apiClient.post<DataEnvelope<PlanningYear>>(`/api/v1/planning-years/${id}/reactivate`, { lock_version })).data.data; }
