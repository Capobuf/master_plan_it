import type { SharedPageProps } from './index';

export interface DashboardModule {
    label: string;
    href: string;
}

export interface DashboardPageProps extends SharedPageProps {
    modules: DashboardModule[];
}
