import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import ExpenseFilters from "./ExpenseFilters";

describe("ExpenseFilters", () => {
  it("has no local year selector and applies search, state, and page size automatically", async () => {
    const onChange = vi.fn();
    render(<ExpenseFilters value={{ page: 3, per_page: 25 }} costCenters={[]} vendors={[]} projects={[]} contracts={[]} showCostCenters showVendors showProjects showContracts onChange={onChange} />);

    expect(screen.queryByLabelText(/Anno di pianificazione/i)).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Cerca Spesa"), { target: { value: "cloud" } });
    await waitFor(() => expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ q: "cloud", page: 1 })), { timeout: 700 });

    fireEvent.change(screen.getByLabelText("Stato"), { target: { value: "closed" } });
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ state: "closed", page: 1 }));

    fireEvent.change(screen.getByLabelText("Risultati per pagina"), { target: { value: "50" } });
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ per_page: 50, page: 1 }));
  });
});
