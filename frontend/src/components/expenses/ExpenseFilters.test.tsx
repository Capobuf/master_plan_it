import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import ExpenseFilters from "./ExpenseFilters";

describe("ExpenseFilters", () => {
  it("keeps the seven automatic filters and excludes pagination controls", async () => {
    const onChange = vi.fn();
    render(<ExpenseFilters value={{ page: 3, per_page: 25 }} costCenters={[]} vendors={[]} projects={[]} contracts={[]} showCostCenters showVendors showProjects showContracts onChange={onChange} />);

    expect(screen.queryByLabelText(/Anno di pianificazione/i)).not.toBeInTheDocument();
    for (const label of ["Cerca Spesa", "Natura", "Centro di Costo", "Fornitore", "Progetto", "Contratto", "Stato"]) {
      expect(screen.getByLabelText(label)).toBeInTheDocument();
    }
    expect(screen.queryByLabelText("Risultati per pagina")).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Cerca Spesa"), { target: { value: "cloud" } });
    await waitFor(() => expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ q: "cloud", page: 1 })), { timeout: 700 });

    fireEvent.change(screen.getByLabelText("Stato"), { target: { value: "closed" } });
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ state: "closed", page: 1 }));
  });
});
