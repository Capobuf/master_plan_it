import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../../api/client";
import * as budgetApi from "../../api/budget";
import { budgetProposalFixture } from "./__fixtures__/budgetApproval";
import BudgetApprovalModal from "./BudgetApprovalModal";

vi.mock("../../api/budget", async (importOriginal) => ({
  ...(await importOriginal<typeof import("../../api/budget")>()),
  approveBudgetProposal: vi.fn(),
}));

const effectiveDateMax = "2026-08-13";

function renderModal(overrides: Partial<React.ComponentProps<typeof BudgetApprovalModal>> = {}) {
  const onApproved = vi.fn().mockResolvedValue(undefined);
  const onReReview = vi.fn().mockResolvedValue(undefined);
  const onClose = vi.fn();
  render(<MemoryRouter><BudgetApprovalModal
    isOpen
    preview={{ ...budgetProposalFixture, effective_date_max: effectiveDateMax }}
    onClose={onClose}
    onApproved={onApproved}
    onReReview={onReReview}
    {...overrides}
  /></MemoryRouter>);
  return { onApproved, onClose, onReReview };
}

describe("BudgetApprovalModal", () => {
  it("confirms the complete reviewed composition with server-provided Tenant-local max date and an optional note", async () => {
    vi.mocked(budgetApi.approveBudgetProposal).mockResolvedValue({
      approval: { id: 91, status: "active", planning_year_id: 25, currency: "EUR", basis: "net", total: budgetProposalFixture.total, effective_date: effectiveDateMax, recorded_at: "2026-08-13T10:30:00Z", approved_by: { id: 5, name: "Mario Rossi" }, note: "Nota facoltativa" },
      budget: { planning_year_id: 25, state: "approved", lock_version: 8 },
      economic_base: { basis: "net", locked_at: "2026-08-13T10:30:00Z" },
    });
    const { onApproved, onClose } = renderModal();

    const date = screen.getByLabelText("Data di efficacia");
    expect(date).toHaveAttribute("max", effectiveDateMax);
    expect(screen.getByRole("heading", { name: "Impatto completo da confermare" })).toBeInTheDocument();
    expect(screen.getByText("Componenti inclusi")).toBeInTheDocument();
    expect(screen.getByText("Componenti esclusi")).toBeInTheDocument();
    fireEvent.change(date, { target: { value: effectiveDateMax } });
    fireEvent.change(screen.getByLabelText("Nota (facoltativa)"), { target: { value: "Nota facoltativa" } });
    fireEvent.click(screen.getByRole("button", { name: "Conferma approvazione" }));

    await waitFor(() => expect(budgetApi.approveBudgetProposal).toHaveBeenCalledWith(25, {
      effective_date: effectiveDateMax,
      note: "Nota facoltativa",
      composition: {
        schema_version: budgetProposalFixture.composition.schema_version,
        fingerprint: budgetProposalFixture.composition.fingerprint,
        versions: budgetProposalFixture.composition.versions,
      },
    }));
    expect(onApproved).toHaveBeenCalledOnce();
    expect(onClose).toHaveBeenCalledOnce();
    expect(screen.queryByRole("checkbox")).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/importo approvato|seleziona componente/i)).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/base economica|approvatore/i)).not.toBeInTheDocument();
  });

  it("keeps an empty composition non-executable while allowing a nonempty zero-total composition", () => {
    const zeroMeasure = { net: "0.00", vat: "0.00", gross: "0.00", official: "0.00" };
    const zeroContributorPreview = {
      ...budgetProposalFixture,
      effective_date_max: effectiveDateMax,
      total: zeroMeasure,
      contributors: budgetProposalFixture.contributors.map((contributor) => ({ ...contributor, amount: zeroMeasure })),
    };
    const { rerender } = render(<MemoryRouter><BudgetApprovalModal
      isOpen
      preview={{ ...budgetProposalFixture, effective_date_max: effectiveDateMax, empty_composition: true, can_approve: false, composition: { ...budgetProposalFixture.composition, contributor_count: 0 }, contributors: [] }}
      onClose={vi.fn()}
      onApproved={vi.fn()}
      onReReview={vi.fn()}
    /></MemoryRouter>);
    expect(screen.getByRole("button", { name: "Conferma approvazione" })).toBeDisabled();

    rerender(<MemoryRouter><BudgetApprovalModal
      isOpen
      preview={zeroContributorPreview}
      onClose={vi.fn()}
      onApproved={vi.fn()}
      onReReview={vi.fn()}
    /></MemoryRouter>);
    fireEvent.change(screen.getByLabelText("Data di efficacia"), { target: { value: effectiveDateMax } });
    expect(screen.getByText(/Totale ufficiale:/)).toHaveTextContent("0,00 € (0.00 Netto, 0.00 IVA, 0.00 Lordo).");
    expect(screen.getByText("2 contributori nella composizione proposta")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Conferma approvazione" })).toBeEnabled();
  });

  it("retains correctable input after a future-date error and exposes its cause in text", async () => {
    vi.mocked(budgetApi.approveBudgetProposal).mockRejectedValue(new ApiError({ message: "I dati inseriti non sono validi.", status: 422, code: "VALIDATION_FAILED", fields: { effective_date: ["La data di efficacia non può superare oggi nel fuso del Tenant."] }, correlationId: "future-date-reference" }));
    renderModal();

    fireEvent.change(screen.getByLabelText("Data di efficacia"), { target: { value: "2026-08-14" } });
    fireEvent.change(screen.getByLabelText("Nota (facoltativa)"), { target: { value: "Mantieni questa nota" } });
    fireEvent.click(screen.getByRole("button", { name: "Conferma approvazione" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/data di efficacia.*fuso del Tenant.*future-date-reference/i);
    expect(screen.getByLabelText("Data di efficacia")).toHaveAttribute("aria-invalid", "true");
    expect(screen.getByLabelText("Data di efficacia")).toHaveValue("2026-08-14");
    expect(screen.getByLabelText("Nota (facoltativa)")).toHaveValue("Mantieni questa nota");
  });

  it("announces the committed approval truthfully and only retries the view refresh when that refresh fails", async () => {
    vi.mocked(budgetApi.approveBudgetProposal).mockResolvedValue({
      approval: { id: 91, status: "active", planning_year_id: 25, currency: "EUR", basis: "net", total: budgetProposalFixture.total, effective_date: effectiveDateMax, recorded_at: "2026-08-13T10:30:00Z", approved_by: { id: 5, name: "Mario Rossi" }, note: null },
      budget: { planning_year_id: 25, state: "approved", lock_version: 8 },
      economic_base: { basis: "net", locked_at: "2026-08-13T10:30:00Z" },
    });
    const refreshFailure = new ApiError({ message: "La vista non è stata aggiornata.", status: 500, correlationId: "refresh-reference" });
    const onApproved = vi.fn().mockRejectedValue(refreshFailure);
    const onClose = vi.fn();
    render(<MemoryRouter><BudgetApprovalModal isOpen preview={{ ...budgetProposalFixture, effective_date_max: effectiveDateMax }} onClose={onClose} onApproved={onApproved} onReReview={vi.fn()} /></MemoryRouter>);

    fireEvent.change(screen.getByLabelText("Data di efficacia"), { target: { value: effectiveDateMax } });
    fireEvent.click(screen.getByRole("button", { name: "Conferma approvazione" }));

    expect(await screen.findByRole("status")).toHaveTextContent(/Approvazione registrata; aggiornamento vista non riuscito.*refresh-reference/i);
    expect(screen.getByRole("button", { name: "Approvazione registrata" })).toBeDisabled();
    expect(onClose).not.toHaveBeenCalled();
    expect(budgetApi.approveBudgetProposal).toHaveBeenCalledOnce();

    fireEvent.click(screen.getByRole("button", { name: "Riprova aggiornamento vista" }));
    await waitFor(() => expect(onApproved).toHaveBeenCalledTimes(2));
    expect(budgetApi.approveBudgetProposal).toHaveBeenCalledOnce();
  });

  it("labels the dialog with its visible title", () => {
    renderModal();
    expect(screen.getByRole("dialog")).toHaveAttribute("aria-labelledby", "budget-approval-modal-title");
  });

  it("requires an explicit accessible refresh and re-review after stale evidence, without silently retrying", async () => {
    vi.mocked(budgetApi.approveBudgetProposal).mockRejectedValue(new ApiError({ message: "La composizione è cambiata.", status: 409, code: "BUDGET_COMPOSITION_STALE", correlationId: "stale-reference" }));
    const { onReReview } = renderModal();

    fireEvent.change(screen.getByLabelText("Data di efficacia"), { target: { value: effectiveDateMax } });
    fireEvent.change(screen.getByLabelText("Nota (facoltativa)"), { target: { value: "Conserva la nota" } });
    fireEvent.click(screen.getByRole("button", { name: "Conferma approvazione" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/riesaminare.*stale-reference/i);
    const review = screen.getByRole("button", { name: "Aggiorna e riesamina la proposta" });
    fireEvent.click(review);
    await waitFor(() => expect(onReReview).toHaveBeenCalledOnce());
    expect(budgetApi.approveBudgetProposal).toHaveBeenCalledOnce();
    expect(screen.getByLabelText("Data di efficacia")).toHaveValue(effectiveDateMax);
    expect(screen.getByLabelText("Nota (facoltativa)")).toHaveValue("Conserva la nota");
  });

  it("keeps the draft and presents refreshed evidence when the parent replaces stale composition evidence", () => {
    const { rerender } = render(<MemoryRouter><BudgetApprovalModal
      isOpen
      preview={{ ...budgetProposalFixture, effective_date_max: effectiveDateMax }}
      onClose={vi.fn()}
      onApproved={vi.fn()}
      onReReview={vi.fn()}
    /></MemoryRouter>);
    fireEvent.change(screen.getByLabelText("Data di efficacia"), { target: { value: effectiveDateMax } });
    fireEvent.change(screen.getByLabelText("Nota (facoltativa)"), { target: { value: "Mantieni il draft" } });

    const refreshed = { ...budgetProposalFixture, effective_date_max: effectiveDateMax, composition: { ...budgetProposalFixture.composition, fingerprint: `sha256:${"e".repeat(64)}` } };
    rerender(<MemoryRouter><BudgetApprovalModal
      isOpen
      preview={refreshed}
      onClose={vi.fn()}
      onApproved={vi.fn()}
      onReReview={vi.fn()}
    /></MemoryRouter>);

    expect(screen.getByText(refreshed.composition.fingerprint)).toBeInTheDocument();
    expect(screen.getByLabelText("Data di efficacia")).toHaveValue(effectiveDateMax);
    expect(screen.getByLabelText("Nota (facoltativa)")).toHaveValue("Mantieni il draft");
  });

  it("retains date and note after an unexpected server error", async () => {
    vi.mocked(budgetApi.approveBudgetProposal).mockRejectedValue(new ApiError({ message: "Il servizio non ha completato la decisione.", status: 500, code: "INTERNAL_ERROR", correlationId: "server-reference" }));
    renderModal();

    fireEvent.change(screen.getByLabelText("Data di efficacia"), { target: { value: effectiveDateMax } });
    fireEvent.change(screen.getByLabelText("Nota (facoltativa)"), { target: { value: "Non perdere questa nota" } });
    fireEvent.click(screen.getByRole("button", { name: "Conferma approvazione" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/servizio non ha completato.*server-reference/i);
    expect(screen.getByLabelText("Data di efficacia")).toHaveValue(effectiveDateMax);
    expect(screen.getByLabelText("Nota (facoltativa)")).toHaveValue("Non perdere questa nota");
  });
});
