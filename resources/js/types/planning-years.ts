import type { SharedPageProps } from './index';

export interface PlanningYearRecord {
    id: number;
    label: number;
    startsAt: string;
    endsAt: string;
    isActive: boolean;
    lockVersion: number;
}

export interface PlanningYearAbilities {
    create: boolean;
    deactivate: boolean;
    reactivate: boolean;
}

export interface PlanningYearIndexPageProps extends SharedPageProps {
    planningYears: PlanningYearRecord[];
    abilities: PlanningYearAbilities;
}

export interface PlanningYearFormData {
    year_label: number;
}
