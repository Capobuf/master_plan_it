import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { ApiError } from "../../api/client";
import {
  getTenantSettings,
  updateTenantSettings,
  type TenantSettings,
} from "../../api/tenantSettings";
import TenantGeneralSettingsForm from "./TenantGeneralSettingsForm";

const refreshContext = vi.hoisted(() => vi.fn());

vi.mock("../../api/tenantSettings", () => ({
  getTenantSettings: vi.fn(),
  updateTenantSettings: vi.fn(),
}));

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({ refreshContext }),
}));

const settings: TenantSettings = {
  tenant_id: 41,
  name: "Acme",
  currency_code: "EUR",
  timezone: "Europe/Rome",
  default_vat_rate: "22.00",
  economic_basis: "net",
  economic_basis_locked_at: "2026-08-12T10:00:00Z",
  deletion_reason_required: false,
  lock_version: 3,
};

describe("TenantGeneralSettingsForm", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    refreshContext.mockResolvedValue(null);
  });

  it("renders loading, read-only fields, budget lock and forward-only guidance", async () => {
    vi.mocked(getTenantSettings).mockResolvedValue(settings);

    render(<TenantGeneralSettingsForm canUpdate={false} />);

    expect(screen.getByText("Caricamento delle impostazioni")).toBeInTheDocument();
    expect(await screen.findByDisplayValue("Acme")).toBeDisabled();
    expect(screen.getByDisplayValue("EUR")).toBeDisabled();
    expect(screen.getByRole("combobox", { name: "Base Economica ufficiale" })).toBeDisabled();
    expect(screen.getByText(/solo ai nuovi elementi senza aliquota esplicita/i)).toBeInTheDocument();
    expect(screen.getByText(/bloccata dopo la prima approvazione/i)).toBeInTheDocument();
    expect(screen.queryByText(/quota allegati/i)).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Salva modifiche" })).not.toBeInTheDocument();
  });

  it("normalizes the VAT, saves the closed payload and refreshes application context", async () => {
    vi.mocked(getTenantSettings).mockResolvedValue({
      ...settings,
      economic_basis_locked_at: null,
    });
    vi.mocked(updateTenantSettings).mockResolvedValue({
      ...settings,
      default_vat_rate: "20.00",
      lock_version: 4,
    });

    render(<TenantGeneralSettingsForm canUpdate />);

    const vat = await screen.findByRole("textbox", { name: "IVA predefinita" });
    fireEvent.change(vat, { target: { value: "20,00" } });
    fireEvent.click(screen.getByRole("button", { name: "Salva modifiche" }));

    await waitFor(() => {
      expect(updateTenantSettings).toHaveBeenCalledWith({
        name: "Acme",
        timezone: "Europe/Rome",
        default_vat_rate: "20.00",
        economic_basis: "net",
        deletion_reason_required: false,
        lock_version: 3,
      });
    });
    expect(refreshContext).toHaveBeenCalledTimes(1);
  });

  it("restores the loaded snapshot when the user cancels", async () => {
    vi.mocked(getTenantSettings).mockResolvedValue(settings);

    render(<TenantGeneralSettingsForm canUpdate />);

    const name = await screen.findByDisplayValue("Acme");
    fireEvent.change(name, { target: { value: "Nome temporaneo" } });
    expect(name).toHaveValue("Nome temporaneo");
    fireEvent.click(screen.getByRole("button", { name: "Annulla" }));

    expect(name).toHaveValue("Acme");
    expect(updateTenantSettings).not.toHaveBeenCalled();
  });

  it("shows loading and stale errors without refreshing context", async () => {
    vi.mocked(getTenantSettings).mockResolvedValue(settings);
    vi.mocked(updateTenantSettings).mockRejectedValue(
      new ApiError({
        message: "Conflitto di versione",
        status: 409,
        code: "STALE_VERSION",
      }),
    );

    render(<TenantGeneralSettingsForm canUpdate />);

    await screen.findByDisplayValue("Acme");
    fireEvent.click(screen.getByRole("button", { name: "Salva modifiche" }));

    expect(await screen.findByText("Conflitto di versione")).toBeInTheDocument();
    expect(refreshContext).not.toHaveBeenCalled();
  });

  it("shows an observable error when loading fails", async () => {
    vi.mocked(getTenantSettings).mockRejectedValue(new Error("Backend non disponibile"));

    render(<TenantGeneralSettingsForm canUpdate={false} />);

    expect(await screen.findByText("Impostazioni non disponibili")).toBeInTheDocument();
    expect(screen.getByText("Backend non disponibile")).toBeInTheDocument();
  });
});
