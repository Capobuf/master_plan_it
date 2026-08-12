import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import ExpenseFilters from "./ExpenseFilters";

describe("ExpenseFilters", () => {
  it("keeps the six target filters and excludes lifecycle and pagination controls", async () => {
    const onChange = vi.fn();
    render(<ExpenseFilters value={{ page: 3, per_page: 25 }} costCenters={[]} vendors={[]} projects={[]} contracts={[]} showCostCenters showVendors showProjects showContracts onChange={onChange} />);

    expect(screen.queryByLabelText(/Anno di pianificazione/i)).not.toBeInTheDocument();
    for (const label of ["Cerca Spesa", "Natura", "Centro di Costo", "Fornitore", "Progetto", "Contratto"]) {
      expect(screen.getByLabelText(label)).toBeInTheDocument();
    }
    expect(screen.queryByLabelText("Risultati per pagina")).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Cerca Spesa"), { target: { value: "cloud" } });
    await waitFor(() => expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ q: "cloud", page: 1 })), { timeout: 700 });

    expect(screen.queryByLabelText("Stato")).not.toBeInTheDocument();
  });
});
