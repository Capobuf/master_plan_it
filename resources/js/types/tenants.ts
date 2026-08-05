import type { SharedPageProps } from "./index";

export type TenantState = "active" | "inactive";

export interface TenantRecord {
    id: number;
    name: string;
    code: string;
    currency: string;
    language: string;
    timezone: string;
    defaultVatRate: string;
    state: TenantState;
    lockVersion: number;
}

export interface TenantIndexPageProps extends SharedPageProps {
    tenants: TenantRecord[];
    abilities: TenantAbilities;
}

export interface TenantAbilities {
    create: boolean;
    update: boolean;
    deactivate: boolean;
    reactivate: boolean;
    enter: boolean;
}

export type TenantCreatePageProps = SharedPageProps;

export interface TenantEditPageProps extends SharedPageProps {
    record: TenantRecord;
}

export interface TenantFormData {
    name: string;
    code: string;
    currency_code: string;
    language_code: string;
    timezone: string;
    default_vat_rate: string;
    lock_version?: number;
}
