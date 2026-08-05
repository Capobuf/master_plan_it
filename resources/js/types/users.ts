import type { PaginatedData, SelectOption, SharedPageProps } from './index';

export interface TenantUserRecord {
    id: number;
    name: string;
    email: string;
    roles: string[];
    roleIds: number[];
    isActive: boolean;
    lockVersion: number;
}

export interface UserIndexPageProps extends SharedPageProps {
    users: PaginatedData<TenantUserRecord>;
    filters: {
        q: string;
    };
    abilities: TenantUserAbilities;
}

export interface UserCreatePageProps extends SharedPageProps {
    roles: SelectOption<number>[];
    abilities: TenantUserAbilities;
}

export interface TenantUserAbilities {
    create: boolean;
    update: boolean;
    deactivate: boolean;
    resetPassword: boolean;
}

export interface UserEditPageProps extends UserCreatePageProps {
    user: TenantUserRecord;
}

export interface UserFormData {
    name: string;
    email: string;
    password?: string;
    roles: number[];
}

export interface ResetUserPasswordFormData {
    password: string;
    password_confirmation: string;
}
