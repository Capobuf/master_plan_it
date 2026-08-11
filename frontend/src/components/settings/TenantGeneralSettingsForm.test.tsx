import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { MemoryRouter } from "react-router";
import {
  getTenantSettings,
  updateTenantSettings,
  type TenantSettings,
} from "../../api/tenantSettings";
import TenantGeneralSettingsForm from "./TenantGeneralSettingsForm";

vi.mock("../../api/tenantSettings", () => ({
  getTenantSettings: vi.fn(),
  updateTenantSettings: vi.fn(),
}));

const settings: TenantSettings = {
  tenant_id: 41,
  name: "Acme",
  currency_code: "EUR",
  timezone: "Europe/Rome",
  default_vat_rate: "22.00",
  budget_basis: "net",
  budget_basis_locked: true,
  budget_basis_lock_reason: "TENANT_BUDGET_BASIS_LOCKED",
  deletion_reason_required: false,
  lock_version: 3,
};

describe("TenantGeneralSettingsForm", () => {
  it("renders loading, VAT guidance, immutable currency and budget lock in read-only mode", async () => {
    vi.mocked(getTenantSettings).mockResolvedValue(settings);
    render(<MemoryRouter><TenantGeneralSettingsForm canUpdate={false} /></MemoryRouter>);

    expect(screen.getByText("Caricamento delle impostazioni")).toBeInTheDocument();
    expect(await screen.findByDisplayValue("Acme")).toBeDisabled();
    expect(screen.getByDisplayValue("EUR")).toBeDisabled();
    expect(screen.getByLabelText("Base Budget ufficiale")).toBeDisabled();
    expect(screen.getByText(/I dati già salvati non vengono modificati/)).toBeInTheDocument();
    expect(screen.getByText(/Bloccata dopo la prima approvazione/)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Salva modifiche" })).not.toBeInTheDocument();
  });

  it("uses explicit save and cancel controls when update is authorized", async () => {
    vi.mocked(getTenantSettings).mockResolvedValue({
      ...settings,
      budget_basis_locked: false,
      budget_basis_lock_reason: null,
    });
    vi.mocked(updateTenantSettings).mockResolvedValue({
      ...settings,
      name: "Acme aggiornata",
      lock_version: 4,
    });
    render(<MemoryRouter><TenantGeneralSettingsForm canUpdate /></MemoryRouter>);

    const name = await screen.findByDisplayValue("Acme");
    fireEvent.change(name, { target: { value: "Acme aggiornata" } });
    fireEvent.click(screen.getByRole("button", { name: "Salva modifiche" }));

    await waitFor(() => {
      expect(updateTenantSettings).toHaveBeenCalledWith(
        expect.objectContaining({
          name: "Acme aggiornata",
          default_vat_rate: "22.00",
          lock_version: 3,
        }),
      );
    });
    expect(screen.getByRole("button", { name: "Annulla" })).toBeInTheDocument();
  });

  it("shows an observable error state when loading fails", async () => {
    vi.mocked(getTenantSettings).mockRejectedValue(new Error("Backend non disponibile"));
    render(<MemoryRouter><TenantGeneralSettingsForm canUpdate={false} /></MemoryRouter>);

    expect(await screen.findByText("Impostazioni non disponibili")).toBeInTheDocument();
  });
});
