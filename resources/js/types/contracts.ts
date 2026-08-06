import type { PaginatedData, SelectOption, SharedPageProps } from "./index";
import type { ConfirmationState, MoneyTotals } from "./expenses";

export type BillingCycle = "monthly" | "annual";

export interface ContractAbilities {
    create: boolean;
    update: boolean;
    delete: boolean;
    synchronize: boolean;
    generate: boolean;
    deleteTerm: boolean;
    resume: boolean;
}

export interface ContractListRecord {
    id: number;
    title: string;
    active: boolean;
    vendorName: string;
    costCenterName: string;
    effectiveStart?: string | null;
    effectiveEnd?: string | null;
    termCount: number;
    generatedExpenseCount?: number;
    lockVersion: number;
}

export interface ContractTermRecord extends MoneyTotals {
    id: number;
    localKey?: string;
    effectiveStart: string;
    effectiveEnd: string;
    billingCycle: BillingCycle;
    quantity: string | null;
    unitPrice: string | null;
    enteredAmount: string;
    amountIncludesVat: boolean;
    vatRate: string;
    autoRenew: boolean;
    lockVersion: number;
    occurrenceAmount?: string;
}

export interface GeneratedContractExpense {
    id: number;
    title: string;
    href?: string;
    occurrenceDate?: string;
    periodLabel?: string;
    sourceKey: string;
    confirmationState?: ConfirmationState;
    state?: string | null;
    isSystemManaged?: boolean;
    gross?: string;
}

export interface SuppressedOccurrence {
    sourceKey: string;
    periodLabel?: string;
    occurrenceDate?: string;
    termId?: number;
    planningYearId?: number;
    suppressedAt?: string | null;
    suppressedBy?: string | null;
}

export interface ContractRevisionActivity {
    id?: number | string;
    operation: string;
    actor?: string | null;
    timestamp: string;
    summary?: string | null;
}

export interface ContractDetailRecord {
    id: number;
    title: string;
    description: string | null;
    active: boolean;
    vendorId: number;
    vendorName: string;
    costCenterId: number;
    costCenterName: string;
    renewalNoticeDays?: number | null;
    renewalDate?: string | null;
    renewalNotes?: string | null;
    lockVersion: number;
    effectiveStart?: string | null;
    effectiveEnd?: string | null;
    terms: ContractTermRecord[];
    generatedExpenses?: GeneratedContractExpense[];
    suppressedOccurrences?: SuppressedOccurrence[];
    revisionActivity?: ContractRevisionActivity[];
}

export interface ContractFormTerm {
    local_key: string;
    id: number | null;
    effective_start: string;
    effective_end: string;
    billing_cycle: BillingCycle;
    quantity: string;
    unit_price: string;
    entered_amount: string;
    amount_includes_vat: boolean;
    vat_rate: string;
    auto_renew: boolean;
    lock_version: number | null;
}

export interface ContractFormData {
    vendor_id: number | "";
    cost_center_id: number | "";
    title: string;
    active: boolean;
    description: string;
    renewal_notice_days: string;
    renewal_date: string;
    renewal_notes: string;
    lock_version: number | null;
    terms: ContractFormTerm[];
}

export interface ContractIndexPageProps extends SharedPageProps {
    contracts: PaginatedData<ContractListRecord> | ContractListRecord[];
    abilities?: Partial<ContractAbilities>;
}

export interface ContractFormPageProps extends SharedPageProps {
    contract?: ContractDetailRecord | null;
    vendors: SelectOption<number>[];
    costCenters: SelectOption<number>[];
    defaults: { vatRate: string };
}

export interface ContractShowPageProps extends SharedPageProps {
    contract: ContractDetailRecord;
    generatedExpenses?: GeneratedContractExpense[];
    suppressedOccurrences?: SuppressedOccurrence[];
    revisionActivity?: ContractRevisionActivity[];
    abilities?: Partial<ContractAbilities>;
    planningYears?: SelectOption<number>[];
    deletionReasonRequired?: boolean;
}
