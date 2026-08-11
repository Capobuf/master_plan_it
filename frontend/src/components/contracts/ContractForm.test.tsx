import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "../../api/client";
import { listContractCostCenters, listContractVendors } from "../../api/contracts";
import { listProjectOptions } from "../../api/projects";
import ContractForm from "./ContractForm";

vi.mock("../../api/contracts", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/contracts")>();
  return {
    ...actual,
    listContractVendors: vi.fn(),
    listContractCostCenters: vi.fn(),
  };
});

vi.mock("../../api/projects", () => ({ listProjectOptions: vi.fn() }));

describe("ContractForm", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listContractVendors).mockResolvedValue({ data: [{ id: 5, name: "Fornitore" }], meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 }, links: { first: null, last: null, prev: null, next: null } });
    vi.mocked(listContractCostCenters).mockResolvedValue({ data: [{ id: 7, name: "IT" }], meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 }, links: { first: null, last: null, prev: null, next: null } });
    vi.mocked(listProjectOptions).mockResolvedValue([]);
  });

  it("highlights all local errors and focuses the first invalid field", async () => {
    render(<ContractForm canSubmit onSubmit={vi.fn()} />);
    await screen.findByRole("option", { name: "Fornitore" });

    fireEvent.click(screen.getByRole("button", { name: "Crea Contratto" }));

    const vendor = screen.getByRole("combobox", { name: "Fornitore" });
    expect(vendor).toHaveAttribute("aria-invalid", "true");
    expect(vendor).toHaveAccessibleDescription("Seleziona un Fornitore.");
    expect(screen.getByRole("combobox", { name: "Centro di costo" })).toHaveAttribute("aria-invalid", "true");
    expect(screen.getByRole("textbox", { name: "Titolo" })).toHaveAttribute("aria-invalid", "true");
    await waitFor(() => expect(document.activeElement).toBe(vendor));
  });

  it("maps an indexed API error to the corresponding term field", async () => {
    const error = new ApiError({
      message: "I dati inseriti non sono validi.",
      status: 422,
      fields: { "terms.0.unit_price": ["The amount must be a decimal with at most 2 places."] },
    });
    render(<ContractForm canSubmit error={error} onSubmit={vi.fn()} />);

    const unitPrice = await screen.findByRole("textbox", { name: "Prezzo unitario" });
    expect(unitPrice).toHaveAttribute("aria-invalid", "true");
    expect(unitPrice).toHaveAccessibleDescription("Inserisci un prezzo valido con massimo 2 decimali.");
    await waitFor(() => expect(document.activeElement).toBe(unitPrice));
  });
});
