import { apiClient, type DataEnvelope } from "./client";

export interface TenantSettings {
  tenant_id: number;
  name: string;
  currency_code: string;
  timezone: string;
  default_vat_rate: string;
  budget_basis: "net" | "gross";
  budget_basis_locked: boolean;
  budget_basis_lock_reason: string | null;
  deletion_reason_required: boolean;
  lock_version: number;
}

export interface TenantSettingsUpdate {
  name: string;
  timezone: string;
  default_vat_rate: string;
  budget_basis: "net" | "gross";
  deletion_reason_required: boolean;
  lock_version: number;
}

export async function getTenantSettings(): Promise<TenantSettings> {
  const response = await apiClient.get<DataEnvelope<TenantSettings>>(
    "/api/v1/tenant-settings",
  );

  return response.data.data;
}

export async function updateTenantSettings(
  input: TenantSettingsUpdate,
): Promise<TenantSettings> {
  const response = await apiClient.put<DataEnvelope<TenantSettings>>(
    "/api/v1/tenant-settings",
    input,
  );

  return response.data.data;
}
