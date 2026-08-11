import {
  apiClient,
  type DataEnvelope,
  type PaginatedData,
  type PaginationParams,
  type Tenant,
  type User,
} from "./client";

export interface ApplicationContextData {
  user: User;
  platformAdministrator: boolean;
  tenant: Tenant | null;
  abilities: string[];
}

export async function getApplicationContext(): Promise<ApplicationContextData> {
  const response = await apiClient.get<DataEnvelope<ApplicationContextData>>(
    "/api/v1/context",
  );

  return response.data.data;
}

export async function listTenants(
  params: PaginationParams = {},
): Promise<PaginatedData<Tenant>> {
  const response = await apiClient.get<PaginatedData<Tenant>>("/api/v1/tenants", {
    params,
  });

  return response.data;
}

export async function enterTenant(tenantId: number): Promise<Tenant> {
  const response = await apiClient.post<DataEnvelope<Tenant>>(
    `/api/v1/tenants/${tenantId}/enter`,
  );

  return response.data.data;
}

export async function leaveTenant(): Promise<void> {
  await apiClient.post("/api/v1/context/leave");
}

