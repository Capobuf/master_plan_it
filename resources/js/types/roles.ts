import type { SelectOption, SharedPageProps } from './index';

export interface TenantRoleRecord {
    id: number;
    name: string;
    abilities: string[];
}

export interface RoleIndexPageProps extends SharedPageProps {
    roles: TenantRoleRecord[];
    abilities: TenantRoleCrudAbilities;
}

export interface TenantRoleCrudAbilities {
    create: boolean;
    update: boolean;
    delete: boolean;
}

export interface RoleCreatePageProps extends SharedPageProps {
    abilities: SelectOption<string>[];
    crudAbilities: TenantRoleCrudAbilities;
}

export interface RoleEditPageProps extends RoleCreatePageProps {
    role: TenantRoleRecord;
}

export interface RoleFormData {
    name: string;
    abilities: string[];
}
