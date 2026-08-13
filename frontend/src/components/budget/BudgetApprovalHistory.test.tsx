import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { MemoryRouter } from "react-router";
import { getBudgetApprovalDetail, getBudgetApprovalHistory, type BudgetApprovalDetail, type BudgetApprovalSummary } from "../../api/budget";
import type { PaginatedData } from "../../api/client";
import BudgetApprovalHistory from "./BudgetApprovalHistory";
import BudgetApprovalDetailDialog from "./BudgetApprovalDetail";
import { annulledApprovalFixture, budgetProposalFixture } from "./__fixtures__/budgetApproval";

vi.mock("../../api/budget", async (importOriginal) => ({
  ...(await importOriginal<typeof import("../../api/budget")>()),
  getBudgetApprovalHistory: vi.fn(),
  getBudgetApprovalDetail: vi.fn(),
}));

const historyPage: PaginatedData<BudgetApprovalSummary> = {
  data: [
    { ...annulledApprovalFixture, planning_year_id: 25, currency: "EUR", basis: "net" },
    { id: 90, status: "active" as const, planning_year_id: 25, currency: "EUR", basis: "net" as const, effective_date: "2026-08-12", recorded_at: "2026-08-12T10:30:00Z", approved_by: { id: 4, name: "Anna Bianchi" }, note: null, total: budgetProposalFixture.total },
  ],
  links: { first: "?page=1", last: "?page=2", prev: null, next: "?page=2" },
  meta: { current_page: 1, last_page: 2, per_page: 25, total: 27 },
};

const storedDetail: BudgetApprovalDetail = {
  id: 91,
  status: "annulled",
  planning_year: { id: 25, year_label: 2026 },
  currency: "EUR",
  basis: "net",
  total: budgetProposalFixture.total,
  effective_date: "2026-08-13",
  recorded_at: "2026-08-13T10:30:00Z",
  approved_by: { id: 5, name: "Mario Rossi" },
  note: "Approvazione iniziale",
  composition: { schema_version: "budget-proposal-composition/v1", fingerprint: `sha256:${"a".repeat(64)}`, contributor_count: 2 },
  contributors: budgetProposalFixture.contributors,
  annulment: { annulled_at: "2026-08-13T11:00:00Z", annulled_by: { id: 5, name: "Mario Rossi" }, note: "Correzione della proposta" },
};

describe("BudgetApprovalHistory", () => {
  beforeEach(() => vi.resetAllMocks());
  it("shows active and annulled stored decisions read-only, then opens the immutable detail", async () => {
    vi.mocked(getBudgetApprovalHistory).mockResolvedValue(historyPage);
    vi.mocked(getBudgetApprovalDetail).mockResolvedValue(storedDetail);
    render(<MemoryRouter><BudgetApprovalHistory planningYearId={25} requestScope="tenant-a" /></MemoryRouter>);

    expect(await screen.findByText("Cronologia delle approvazioni")).toBeInTheDocument();
    expect(screen.getByText("Annullata")).toBeInTheDocument();
    expect(screen.getByText("Attiva")).toBeInTheDocument();
    expect(screen.getByText("Correzione della proposta")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /annulla|modifica/i })).not.toBeInTheDocument();

    fireEvent.click(screen.getAllByRole("button", { name: "Apri fotografia" })[0]);
    expect(await screen.findByRole("dialog", { name: "Fotografia approvazione 91" })).toBeInTheDocument();
    expect(screen.getByText("Componenti registrati")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Licenze annuali/i })).toHaveAttribute("href", "/spese/81?planning_year_id=25");
    fireEvent.change(screen.getByLabelText("Filtra componenti registrati"), { target: { value: "Licenze" } });
    expect(screen.getByLabelText("Totale selezione")).toHaveTextContent("120,00 €");
    expect(screen.getByText("Previsto completo registrato").parentElement).toHaveTextContent("3.620,00 €");
    expect(screen.queryByRole("button", { name: /annulla|modifica|approva/i })).not.toBeInTheDocument();
  });

  it("uses the exact paginated list contract and advances only through server pagination", async () => {
    vi.mocked(getBudgetApprovalHistory)
      .mockResolvedValueOnce(historyPage)
      .mockResolvedValueOnce({ ...historyPage, links: { ...historyPage.links, prev: "?page=1", next: null }, meta: { ...historyPage.meta, current_page: 2 } });
    render(<MemoryRouter><BudgetApprovalHistory planningYearId={25} requestScope="tenant-a" /></MemoryRouter>);

    await waitFor(() => expect(getBudgetApprovalHistory).toHaveBeenCalledWith(25, { page: 1, per_page: 25 }));
    fireEvent.click(screen.getByRole("button", { name: "Successiva" }));
    await waitFor(() => expect(getBudgetApprovalHistory).toHaveBeenLastCalledWith(25, { page: 2, per_page: 25 }));
  });

  it("returns to the displayed page after a failed next-page request and retries without skipping", async () => {
    const pageTwo = { ...historyPage, links: { ...historyPage.links, prev: "?page=1", next: null }, meta: { ...historyPage.meta, current_page: 2 } };
    vi.mocked(getBudgetApprovalHistory)
      .mockResolvedValueOnce(historyPage)
      .mockRejectedValueOnce(new Error("network"))
      .mockResolvedValueOnce(historyPage)
      .mockResolvedValueOnce(pageTwo);
    render(<MemoryRouter><BudgetApprovalHistory planningYearId={25} requestScope="tenant-a" /></MemoryRouter>);

    await screen.findByText("Pagina 1 di 2", { exact: false });
    fireEvent.click(screen.getByRole("button", { name: "Successiva" }));
    expect(await screen.findByRole("button", { name: "Riprova" })).toBeInTheDocument();
    expect(screen.getByText("Pagina 1 di 2", { exact: false })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Riprova" }));
    await waitFor(() => expect(getBudgetApprovalHistory).toHaveBeenLastCalledWith(25, { page: 2, per_page: 25 }));
    expect(await screen.findByText("Pagina 2 di 2", { exact: false })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Precedente" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Successiva" })).toBeDisabled();
  });

  it("discards a late Tenant-A response after Tenant B selects the same PlanningYear", async () => {
    let resolveFirst: (value: typeof historyPage) => void = () => undefined;
    const first = new Promise<typeof historyPage>((resolve) => { resolveFirst = resolve; });
    const yearB = { ...historyPage, data: [{ ...historyPage.data[1], approved_by: { id: 6, name: "Giulia Verdi" } }] };
    vi.mocked(getBudgetApprovalHistory).mockReturnValueOnce(first).mockResolvedValueOnce(yearB);
    const rendered = render(<MemoryRouter><BudgetApprovalHistory planningYearId={25} requestScope="tenant-a" /></MemoryRouter>);
    rendered.rerender(<MemoryRouter><BudgetApprovalHistory planningYearId={25} requestScope="tenant-b" /></MemoryRouter>);

    expect(await screen.findByText("Giulia Verdi")).toBeInTheDocument();
    resolveFirst(historyPage);
    await waitFor(() => expect(screen.queryByText("Mario Rossi")).not.toBeInTheDocument());
  });

  it("derives a filter-only selection total with exact signed decimals from stored contributors", () => {
    const large = { net: "99999999999999999.99", vat: "0.00", gross: "99999999999999999.99", official: "99999999999999999.99" };
    const offset = { net: "-0.01", vat: "0.00", gross: "-0.01", official: "-0.01" };
    const detail: BudgetApprovalDetail = {
      ...storedDetail,
      contributors: [
        { ...budgetProposalFixture.contributors[0], source_identity: "stored-large", expense: { id: 81, title: "Gruppo grande" }, amount: large },
        { ...budgetProposalFixture.contributors[1], source_identity: "stored-offset", expense: { id: 82, title: "Gruppo grande" }, amount: offset, drill_down: { authorized: true, href: "https://other-tenant.invalid/expenses/82" } },
        { ...budgetProposalFixture.contributors[1], source_identity: "stored-zero", expense: { id: 83, title: "Zero separato" }, amount: { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" } },
      ],
    };
    render(<MemoryRouter><BudgetApprovalDetailDialog approval={detail} onClose={() => undefined} /></MemoryRouter>);

    fireEvent.change(screen.getByLabelText("Filtra componenti registrati"), { target: { value: "Gruppo grande" } });
    expect(screen.getByLabelText("Totale selezione")).toHaveTextContent("99.999.999.999.999.999,98 €");
    expect(screen.queryAllByRole("link").map((link) => link.getAttribute("href"))).not.toContain("https://other-tenant.invalid/expenses/82");
    fireEvent.change(screen.getByLabelText("Filtra componenti registrati"), { target: { value: "Zero separato" } });
    expect(screen.getByLabelText("Totale selezione")).toHaveTextContent("Netto selezione0,00 €");
    expect(screen.getByLabelText("Totale selezione")).toHaveTextContent("Ufficiale selezione0,00 €");
    fireEvent.change(screen.getByLabelText("Filtra componenti registrati"), { target: { value: "nessuna corrispondenza" } });
    expect(screen.getByLabelText("Totale selezione")).toHaveTextContent("Netto selezione0,00 €");
    expect(screen.getByLabelText("Totale selezione")).toHaveTextContent("Ufficiale selezione0,00 €");
  });

  it("keeps only the latest detail selection when two stored snapshots resolve out of order", async () => {
    let resolveFirst: (value: BudgetApprovalDetail) => void = () => undefined;
    let resolveSecond: (value: BudgetApprovalDetail) => void = () => undefined;
    vi.mocked(getBudgetApprovalHistory).mockResolvedValue(historyPage);
    vi.mocked(getBudgetApprovalDetail)
      .mockReturnValueOnce(new Promise((resolve) => { resolveFirst = resolve; }))
      .mockReturnValueOnce(new Promise((resolve) => { resolveSecond = resolve; }));
    render(<MemoryRouter><BudgetApprovalHistory planningYearId={25} requestScope="tenant-a" /></MemoryRouter>);

    await screen.findAllByRole("button", { name: "Apri fotografia" });
    const [first, second] = screen.getAllByRole("button", { name: "Apri fotografia" });
    fireEvent.click(first);
    fireEvent.click(second);
    resolveSecond({ ...storedDetail, id: 90, approved_by: { id: 4, name: "Anna Bianchi" } });
    expect(await screen.findByRole("dialog", { name: "Fotografia approvazione 90" })).toBeInTheDocument();
    resolveFirst(storedDetail);
    await waitFor(() => expect(screen.queryByRole("dialog", { name: "Fotografia approvazione 91" })).not.toBeInTheDocument());
  });
});
