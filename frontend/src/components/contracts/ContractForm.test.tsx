import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "../../api/client";
import { listContractCostCenters, listContractVendors, type Contract } from "../../api/contracts";
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

  it("keeps VAT omitted for the first and every newly added term", async () => {
    render(<ContractForm canSubmit onSubmit={vi.fn()} />);

    const initialVat = await screen.findByRole("textbox", { name: "Aliquota IVA" });
    expect(initialVat).toHaveValue("");

    fireEvent.click(screen.getByRole("button", { name: "Aggiungi Termine" }));

    expect(screen.getAllByRole("textbox", { name: "Aliquota IVA" })).toHaveLength(2);
    screen.getAllByRole("textbox", { name: "Aliquota IVA" }).forEach((input) => {
      expect(input).toHaveValue("");
    });
  });

  it("preserves persisted VAT while newly added terms remain omitted", async () => {
    const contract = {
      id: 18,
      vendor_id: 5,
      cost_center_id: 7,
      project_id: null,
      title: "Contratto corrente",
      description: null,
      active: true,
      renewal_date: null,
      renewal_notice_days: null,
      renewal_notes: null,
      lock_version: 2,
      terms: [{
        id: 31,
        local_key: "persisted-term",
        effective_start: "2026-01-01",
        effective_end: "2026-12-31",
        billing_cycle: "monthly",
        quantity: null,
        unit_price: null,
        entered_amount: "100.00",
        amount_includes_vat: false,
        vat_rate: "22.00",
        auto_renew: false,
        net: "100.00",
        vat: "22.00",
        gross: "122.00",
        currency: "EUR",
        official_basis: "net",
        lock_version: 4,
      }],
    } as Contract;

    render(<ContractForm contract={contract} canSubmit onSubmit={vi.fn()} />);

    expect(await screen.findByRole("textbox", { name: "Aliquota IVA" })).toHaveValue("22,00");
    fireEvent.click(screen.getByRole("button", { name: "Aggiungi Termine" }));

    const vatInputs = screen.getAllByRole("textbox", { name: "Aliquota IVA" });
    expect(vatInputs[0]).toHaveValue("22,00");
    expect(vatInputs[1]).toHaveValue("");
  });
});
