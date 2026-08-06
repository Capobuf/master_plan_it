export interface AuthenticatedUser {
    id: number;
    name: string;
    email: string;
    isPlatformAdministrator: boolean;
}

export interface CurrentTenant {
    id: number;
    name: string;
    code: string;
    currency: string;
    language: string;
    timezone: string;
}

export interface NavigationAbilities {
    canViewPlatformTenants: boolean;
    canViewDashboard: boolean;
    canManageUsers: boolean;
    canManageRoles: boolean;
    canViewPlanningYears: boolean;
    canViewCostCenters: boolean;
    canViewVendors: boolean;
    canViewExpenses: boolean;
    canViewContracts: boolean;
    canCreateContracts: boolean;
    canViewBudget: boolean;
    canCreateExpenses: boolean;
    canUpdateExpenses: boolean;
}

export interface SharedPageProps {
    [key: string]: unknown;
    auth: {
        user: AuthenticatedUser | null;
    };
    tenant: {
        current: CurrentTenant | null;
    };
    navigation: NavigationAbilities;
    flash: {
        success: string | null;
        error: string | null;
    };
    diagnostics: {
        correlationId: string;
    };
    errors: Record<string, string>;
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

export interface SelectOption<TValue extends number | string = number> {
    value: TValue;
    label: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

export * from "./auth";
export * from "./cost-centers";
export * from "./contracts";
export * from "./dashboard";
export * from "./expenses";
export * from "./planning-years";
export * from "./revisions";
export * from "./roles";
export * from "./tenants";
export * from "./users";
export * from "./vendors";
