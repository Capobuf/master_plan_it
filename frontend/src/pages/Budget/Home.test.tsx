import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { getBudget, getBudgetApprovalPreview, type AnnualBudget, type BudgetApprovalPreview } from "../../api/budget";
import { annualBudgetFixture, budgetProposalFixture } from "../../components/budget/__fixtures__/budgetApproval";
import BudgetHome from "./Home";

let selectedPlanningYearId = 7;

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    data: { tenant: { id: 1, timezone: "Europe/Rome" } },
    loading: false,
    hasAbility: () => true,
  }),
}));

vi.mock("../../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({ selectedPlanningYearId, loading: false }),
}));

vi.mock("../../api/budget", () => ({ getBudget: vi.fn(), getBudgetApprovalPreview: vi.fn() }));
vi.mock("../../components/budget/BudgetView", () => ({
  default: ({ dataset }: { dataset: AnnualBudget }) => <p>Budget anno {dataset.planning_year.id}</p>,
}));
vi.mock("../../components/common/PageBreadCrumb", () => ({ default: () => null }));
vi.mock("../../components/common/PageMeta", () => ({ default: () => null }));

function overview(id: number): AnnualBudget {
  return { ...annualBudgetFixture, planning_year: { ...annualBudgetFixture.planning_year, id }, proposal: { ...annualBudgetFixture.proposal, composition: { ...annualBudgetFixture.proposal.composition, versions: { ...annualBudgetFixture.proposal.composition.versions } } } };
}

function preview(id: number): BudgetApprovalPreview {
  return { ...budgetProposalFixture, planning_year: { ...budgetProposalFixture.planning_year, id }, composition: { ...budgetProposalFixture.composition, versions: { ...budgetProposalFixture.composition.versions } } };
}

describe("BudgetHome workspace responses", () => {
  beforeEach(() => {
    selectedPlanningYearId = 7;
    vi.clearAllMocks();
  });

  it("discards a late prior Planning Year pair", async () => {
    let resolveOverview!: (value: AnnualBudget) => void;
    let resolvePreview!: (value: BudgetApprovalPreview) => void;
    vi.mocked(getBudget).mockImplementation((query) => query.planning_year_id === 7 ? new Promise((resolve) => { resolveOverview = resolve; }) : Promise.resolve(overview(8)));
    vi.mocked(getBudgetApprovalPreview).mockImplementation((id) => id === 7 ? new Promise((resolve) => { resolvePreview = resolve; }) : Promise.resolve(preview(8)));

    const view = render(<BudgetHome />);
    await waitFor(() => expect(getBudget).toHaveBeenCalledWith({ planning_year_id: 7 }));
    expect(getBudgetApprovalPreview).toHaveBeenCalledWith(7);

    selectedPlanningYearId = 8;
    view.rerender(<BudgetHome />);
    expect(await screen.findByText("Budget anno 8")).toBeInTheDocument();

    resolveOverview(overview(7));
    resolvePreview(preview(7));
    await waitFor(() => expect(screen.queryByText("Budget anno 7")).not.toBeInTheDocument());
  });

  it("does not render an overview and impact from different composition evidence", async () => {
    vi.mocked(getBudget).mockResolvedValue(overview(7));
    vi.mocked(getBudgetApprovalPreview).mockResolvedValue({ ...preview(7), composition: { ...preview(7).composition, fingerprint: `sha256:${"b".repeat(64)}` } });

    render(<BudgetHome />);
    expect(await screen.findByText("Caricamento non riuscito")).toBeInTheDocument();
    expect(screen.getByText(/proposta è cambiata durante l’aggiornamento/i)).toBeInTheDocument();
    expect(screen.queryByText("Budget anno 7")).not.toBeInTheDocument();
  });

  it("rejects a non-contributor evidence change even when composition and lock version match", async () => {
    vi.mocked(getBudget).mockResolvedValue(overview(7));
    vi.mocked(getBudgetApprovalPreview).mockResolvedValue({
      ...preview(7),
      surface_fingerprint: `sha256:${"d".repeat(64)}`,
    });

    render(<BudgetHome />);

    expect(await screen.findByText("Caricamento non riuscito")).toBeInTheDocument();
    expect(screen.getByText(/proposta è cambiata durante l’aggiornamento/i)).toBeInTheDocument();
    expect(screen.queryByText("Budget anno 7")).not.toBeInTheDocument();
  });

  it("rejects an incomplete surface token rather than accepting a mixed response", async () => {
    vi.mocked(getBudget).mockResolvedValue(overview(7));
    const incompletePreview = { ...preview(7) } as Partial<BudgetApprovalPreview>;
    delete incompletePreview.surface_fingerprint;
    vi.mocked(getBudgetApprovalPreview).mockResolvedValue(incompletePreview as BudgetApprovalPreview);

    render(<BudgetHome />);

    expect(await screen.findByText("Caricamento non riuscito")).toBeInTheDocument();
    expect(screen.queryByText("Budget anno 7")).not.toBeInTheDocument();
  });
});
