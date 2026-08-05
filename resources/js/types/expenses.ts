import type { PaginatedData, SharedPageProps } from './index';

export interface ExpenseRegisterRecord {
    id: number;
    planningYearId: number;
    planningYearLabel: number;
    costCenterId: number;
    costCenterName: string;
    kind: string;
    title: string;
    rowCount: number;
    net: string;
    vat: string;
    gross: string;
}

export interface ExpenseYearOption {
    value: number;
    label: string;
    active: boolean;
}

export interface MoneyTotals {
    net: string;
    vat: string;
    gross: string;
}

export interface ExpenseIndexPageProps extends SharedPageProps {
    expenses: PaginatedData<ExpenseRegisterRecord>;
    yearOptions: ExpenseYearOption[];
    selectedYear: number | null;
    totals: MoneyTotals;
}

export interface ExpenseRowRecord extends MoneyTotals {
    id: number;
    position: number;
    vendorId: number | null;
    type: string;
    confirmationState: string | null;
    description: string;
}

export interface ExpenseDetailRecord extends MoneyTotals {
    id: number;
    planningYearId: number;
    planningYearLabel: number;
    costCenterId: number;
    costCenterName: string;
    kind: string;
    title: string;
    notes: string | null;
    rows: ExpenseRowRecord[];
}

export interface ExpenseShowPageProps extends SharedPageProps {
    expense: ExpenseDetailRecord;
}
