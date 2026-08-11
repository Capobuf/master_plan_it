import { apiClient, type DataEnvelope, type PaginatedData } from "./client";
import type { OperationalRevision, RevisionComparison } from "./revisions";

export type ProjectStage = "idea" | "proposed" | "approved" | "deferred" | "rejected";

export interface ProjectReference {
  id: number;
  name: string;
}

export interface ProjectPlanningYear {
  id: number;
  year_label: number;
  active: boolean;
}

export interface ProjectExpense {
  id: number;
  title: string;
  kind: string;
  planning_year_id: number;
  planning_year_label: number;
}

export type ProjectRevision = OperationalRevision;

export interface Project {
  id: number;
  title: string;
  stage: ProjectStage;
  cost_center_id: number;
  cost_center: ProjectReference | null;
  deferred_target_planning_year_id: number | null;
  deferred_target_planning_year: ProjectPlanningYear | null;
  expense_count: number;
  expenses: ProjectExpense[];
  lock_version: number;
  revision_activity: ProjectRevision[];
}

export interface ProjectWrite {
  title: string;
  cost_center_id: number;
  stage: ProjectStage;
  deferred_target_planning_year_id: number | null;
}

export interface ProjectUpdate extends ProjectWrite {
  lock_version: number;
}

export interface ProjectListParams {
  page?: number;
  per_page?: number;
}

export interface ProjectLookupOption {
  id: number;
  title: string;
  stage: ProjectStage;
}

export type ProjectRevisionComparison = RevisionComparison;

export async function listProjects(params: ProjectListParams = {}): Promise<PaginatedData<Project>> {
  const response = await apiClient.get<PaginatedData<Project>>("/api/v1/projects", { params });
  return response.data;
}

export async function listProjectOptions(): Promise<ProjectLookupOption[]> {
  const items: ProjectLookupOption[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await listProjects({ page, per_page: 100 });
    items.push(...response.data.map(({ id, title, stage }) => ({ id, title, stage })));
    page = response.meta.current_page + 1;
    lastPage = response.meta.last_page;
  } while (page <= lastPage);
  return items;
}

export async function getProject(projectId: number): Promise<Project> {
  const response = await apiClient.get<DataEnvelope<Project>>(`/api/v1/projects/${projectId}`);
  return response.data.data;
}

export async function createProject(input: ProjectWrite): Promise<Project> {
  const response = await apiClient.post<DataEnvelope<Project>>("/api/v1/projects", input);
  return response.data.data;
}

export async function updateProject(projectId: number, input: ProjectUpdate): Promise<Project> {
  const response = await apiClient.put<DataEnvelope<Project>>(`/api/v1/projects/${projectId}`, input);
  return response.data.data;
}

export async function deleteProject(projectId: number, input: { lock_version: number; deletion_reason?: string }): Promise<void> {
  await apiClient.delete(`/api/v1/projects/${projectId}`, { data: input });
}

export async function getProjectHistory(projectId: number): Promise<PaginatedData<ProjectRevision>> {
  const response = await apiClient.get<PaginatedData<ProjectRevision>>(`/api/v1/projects/${projectId}/history`, { params: { per_page: 10 } });
  return response.data;
}

export async function getProjectRevision(projectId: number, revisionId: number): Promise<ProjectRevisionComparison> {
  const response = await apiClient.get<DataEnvelope<ProjectRevisionComparison>>(`/api/v1/projects/${projectId}/history/${revisionId}`);
  return response.data.data;
}

export async function restoreProjectRevision(projectId: number, revisionId: number, lockVersion: number): Promise<Project> {
  const response = await apiClient.post<DataEnvelope<Project>>(`/api/v1/projects/${projectId}/history/${revisionId}/restore`, { lock_version: lockVersion });
  return response.data.data;
}
