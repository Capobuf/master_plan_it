import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useState } from "react";
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
  default: function BudgetViewMock({ dataset, preview, onRefresh }: { dataset: AnnualBudget; preview: BudgetApprovalPreview; onRefresh: () => Promise<void> | void }) {
    const [date, setDate] = useState("");
    const [note, setNote] = useState("");
    return <div>
      <p>Budget anno {dataset.planning_year.id}</p>
      <p>Stato {dataset.planning_year.state}</p>
      <p>Previsto approvato {dataset.approved_snapshot?.total.official ?? "—"}</p>
      <p>Proposta corrente {dataset.proposal.total.official}</p>
      <p>Fingerprint {preview.composition.fingerprint}</p>
      <label htmlFor="test-draft-date">Data di efficacia</label><input id="test-draft-date" value={date} onChange={(event) => setDate(event.target.value)} />
      <label htmlFor="test-draft-note">Nota (facoltativa)</label><textarea id="test-draft-note" value={note} onChange={(event) => setNote(event.target.value)} />
      <button type="button" onClick={() => void onRefresh()}>Aggiorna dopo approvazione</button>
    </div>;
  },
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

  it("refreshes the Home/View pair after approval and replaces Preparation with the immutable approved snapshot", async () => {
    const preparation = overview(7);
    const approved: AnnualBudget = {
      ...overview(7),
      planning_year: { ...overview(7).planning_year, state: "approved", lock_version: 8 },
      approved_snapshot: { id: 91, status: "active", planning_year: { id: 7, year_label: 2026 }, currency: "EUR", basis: "net", effective_date: "2026-08-13", recorded_at: "2026-08-13T10:30:00Z", total: { net: "120.00", vat: "26.40", gross: "146.40", official: "120.00" } },
      actions: { ...overview(7).actions, can_approve: false },
    };
    vi.mocked(getBudget).mockResolvedValueOnce(preparation).mockResolvedValueOnce(approved);
    vi.mocked(getBudgetApprovalPreview).mockResolvedValueOnce(preview(7)).mockResolvedValueOnce({ ...preview(7), planning_year: { ...preview(7).planning_year, state: "approved", lock_version: 8 }, can_approve: false });

    render(<BudgetHome />);
    expect(await screen.findByText("Stato preparation")).toBeInTheDocument();
    expect(screen.getByText("Previsto approvato —")).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Aggiorna dopo approvazione" }));

    await waitFor(() => expect(getBudget).toHaveBeenCalledTimes(2));
    expect(await screen.findByText("Stato approved")).toBeInTheDocument();
    expect(screen.getByText("Previsto approvato 120.00")).toBeInTheDocument();
    expect(screen.getByText("Proposta corrente 3620.00")).toBeInTheDocument();
  });

  it("preserves the approval draft while a stale re-review refresh replaces the composition fingerprint", async () => {
    const refreshedPreview = {
      ...preview(7),
      composition: { ...preview(7).composition, fingerprint: `sha256:${"e".repeat(64)}` },
    };
    const refreshedOverview = {
      ...overview(7),
      proposal: { ...overview(7).proposal, composition: refreshedPreview.composition },
    };
    vi.mocked(getBudget).mockResolvedValueOnce(overview(7)).mockResolvedValueOnce(refreshedOverview);
    vi.mocked(getBudgetApprovalPreview).mockResolvedValueOnce(preview(7)).mockResolvedValueOnce(refreshedPreview);

    render(<BudgetHome />);
    await screen.findByText(`Fingerprint ${budgetProposalFixture.composition.fingerprint}`);
    fireEvent.change(screen.getByLabelText("Data di efficacia"), { target: { value: "2026-08-13" } });
    fireEvent.change(screen.getByLabelText("Nota (facoltativa)"), { target: { value: "Mantieni il draft" } });
    fireEvent.click(screen.getByRole("button", { name: "Aggiorna dopo approvazione" }));

    expect(await screen.findByText(`Fingerprint ${refreshedPreview.composition.fingerprint}`)).toBeInTheDocument();
    expect(screen.getByLabelText("Data di efficacia")).toHaveValue("2026-08-13");
    expect(screen.getByLabelText("Nota (facoltativa)")).toHaveValue("Mantieni il draft");
  });
});
