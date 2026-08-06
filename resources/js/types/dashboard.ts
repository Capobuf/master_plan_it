import type { SelectOption, SharedPageProps } from "./index";

export interface DashboardKpi {
    label: string;
    value: string;
    description?: string | null;
}

export interface ChartSeries {
    label: string;
    data: number[];
    backgroundColor?: string | string[];
    borderColor?: string;
}

export interface DashboardChart {
    labels: string[];
    datasets: ChartSeries[];
}

export interface DashboardTableRow {
    id?: number;
    label: string;
    href?: string;
    secondary?: string | null;
    value?: string | null;
    state?: string | null;
    date?: string | null;
}

export interface EconomicSummaryProps {
    officialBasis: "net" | "gross";
    officialCurrentPosition: string;
    net: string;
    vat: string;
    gross: string;
    estimate: string;
    quote: string;
    actual: string;
    actualToConfirm: string;
    actualConfirmed: string;
    plafondAllocated: string;
    plafondConsumed: string;
    plafondResidual: string;
    plafondOverrun: string;
}

export interface DashboardPageProps extends SharedPageProps {
    yearOptions: Array<SelectOption<number> & { active?: boolean }>;
    selectedYear: number | null;
    summary?: EconomicSummaryProps | null;
    hasEconomicData?: boolean;
    charts?: {
        monthly?: DashboardChart;
        byType?: DashboardChart;
        byCostCenter?: DashboardChart;
    };
    topCostCenters?: DashboardTableRow[];
    recentExpenses?: DashboardTableRow[];
    generatedExpensesToConfirm?: DashboardTableRow[];
    activeContracts?: DashboardTableRow[];
    upcomingContractEvents?: DashboardTableRow[];
    generatedAt?: string | null;
    stale?: boolean;
    error?: string | null;
}
