import { apiClient, type DataEnvelope } from "./client";
export interface TenantSettings { tenant_id:number; name:string; currency_code:string; timezone:string; default_vat_rate:string; budget_basis:"net"|"gross"; budget_basis_locked:boolean; budget_basis_lock_reason:string|null; deletion_reason_required:boolean; lock_version:number; }
export type TenantSettingsUpdate=Pick<TenantSettings,"name"|"timezone"|"default_vat_rate"|"budget_basis"|"deletion_reason_required"|"lock_version">;
export async function getTenantSettings():Promise<TenantSettings>{return (await apiClient.get<DataEnvelope<TenantSettings>>("/api/v1/tenant-settings")).data.data;}
export async function updateTenantSettings(input:TenantSettingsUpdate):Promise<TenantSettings>{return (await apiClient.put<DataEnvelope<TenantSettings>>("/api/v1/tenant-settings",input)).data.data;}
