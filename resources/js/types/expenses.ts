import type { PaginatedData, SelectOption, SharedPageProps } from "./index";

export type ExpenseKind = "ordinary" | "plafond";
export type ExpenseRowType = "estimate" | "quote" | "actual";
export type ExpenseDistribution = "all" | "start" | "end";
export type ConfirmationState = "to_confirm" | "confirmed" | null;

export interface MoneyTotals {
    net: string;
    vat: string;
    gross: string;
}

export interface ExpenseRegisterRecord extends MoneyTotals {
    id: number;
    planningYearId: number;
    planningYearLabel: number | string;
    costCenterId: number;
    costCenterName: string;
    kind: ExpenseKind;
    title: string;
    rowCount: number;
    contractId?: number | null;
    contractTitle?: string | null;
    contractHref?: string | null;
}

export interface ExpenseYearOption extends SelectOption<number> {
    active: boolean;
}

export interface ExpenseRowRecord extends MoneyTotals {
    id: number;
    localKey?: string;
    position: number;
    vendorId: number | null;
    vendorName?: string | null;
    type: ExpenseRowType;
    confirmationState: ConfirmationState;
    description: string;
    quantity?: string | null;
    unitPrice?: string | null;
    enteredAmount?: string;
    amountIncludesVat?: boolean;
    vatRate?: string;
    isExtra?: boolean;
    fundedPlafondExpenseId?: number | null;
    fundedPlafondTitle?: string | null;
    spendDate?: string | null;
    periodStart?: string | null;
    periodEnd?: string | null;
    distribution?: ExpenseDistribution | null;
    externalReference?: string | null;
    lockVersion?: number;
    isSystemManaged?: boolean;
    sourceKey?: string | null;
    contractTermId?: number | null;
    canConfirm?: boolean;
}

export interface ExpenseDetailRecord extends MoneyTotals {
    id: number;
    planningYearId: number;
    planningYearLabel: number | string;
    costCenterId: number;
    costCenterName: string;
    kind: ExpenseKind;
    title: string;
    notes: string | null;
    contractId?: number | null;
    contractTitle?: string | null;
    contractHref?: string | null;
    contractIsCurrent?: boolean;
    lockVersion?: number;
    rows: ExpenseRowRecord[];
}

export interface ExpenseAbilities {
    create: boolean;
    update: boolean;
    delete: boolean;
    confirmActual: boolean;
}

export interface ExpenseIndexPageProps extends SharedPageProps {
    expenses: PaginatedData<ExpenseRegisterRecord>;
    yearOptions: ExpenseYearOption[];
    selectedYear: number | null;
    totals: MoneyTotals;
    abilities?: Partial<ExpenseAbilities>;
}

export interface ExpenseShowPageProps extends SharedPageProps {
    expense: ExpenseDetailRecord;
    abilities?: Partial<ExpenseAbilities>;
}

export interface ExpenseFormRow {
    local_key: string;
    id: number | null;
    position: number;
    vendor_id: number | "";
    type: ExpenseRowType;
    description: string;
    quantity: string;
    unit_price: string;
    entered_amount: string;
    amount_includes_vat: boolean;
    vat_rate: string;
    is_extra: boolean;
    funded_plafond_expense_id: number | "";
    spend_date: string;
    period_start: string;
    period_end: string;
    distribution: ExpenseDistribution | "";
    external_reference: string;
    lock_version: number | null;
}

export interface ExpenseFormData {
    planning_year_id: number | "";
    cost_center_id: number | "";
    kind: ExpenseKind;
    title: string;
    notes: string;
    contract_id: number | "";
    lock_version: number | null;
    rows: ExpenseFormRow[];
}

export interface ExpenseFormDefaults {
    vatRate: string;
}

export interface ExpenseFormPageProps extends SharedPageProps {
    expense?: ExpenseDetailRecord | null;
    planningYears: ExpenseYearOption[];
    costCenters: SelectOption<number>[];
    vendors: SelectOption<number>[];
    plafonds: SelectOption<number>[];
    contracts?: SelectOption<number>[];
    defaults: ExpenseFormDefaults;
}
