import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";

import {
  createTenant,
  listTenants,
  updateTenant,
} from "../../api/tenants";
import { enterTenant } from "../../api/context";
import type { Tenant } from "../../api/client";
import { routes } from "../../navigation/routes";
import TenantsView from "./TenantsView";

const applicationContext = vi.hoisted(() => ({
  refreshContext: vi.fn(),
}));

vi.mock("../../api/tenants", () => ({
  listTenants: vi.fn(),
  createTenant: vi.fn(),
  updateTenant: vi.fn(),
  deactivateTenant: vi.fn(),
  reactivateTenant: vi.fn(),
}));

vi.mock("../../api/context", () => ({
  enterTenant: vi.fn(),
}));

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    refreshContext: applicationContext.refreshContext,
  }),
}));

const tenant: Tenant = {
  id: 17,
  name: "Acme Italia",
  code: "acme-it",
  currency_code: "EUR",
  language_code: "it",
  timezone: "Europe/Rome",
  default_vat_rate: "22.00",
  budget_basis: "net",
  state: "active",
  lock_version: 4,
};

const createdTenant: Tenant = {
  ...tenant,
  id: 18,
  name: "Nuovo Tenant",
  code: "nuovo-tenant",
  default_vat_rate: "20.50",
  lock_version: 1,
};

const tenantPage = {
  data: [tenant],
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 100,
    total: 1,
  },
  links: { first: null, last: null, prev: null, next: null },
};

function renderView() {
  return render(
    <MemoryRouter initialEntries={[routes.tenant]}>
      <Routes>
        <Route
          path={routes.tenant}
          element={<TenantsView canView canCreate canUpdate canDeactivate={false} canReactivate={false} />}
        />
        <Route path={routes.panoramica} element={<p>Panoramica Tenant</p>} />
      </Routes>
    </MemoryRouter>,
  );
}

describe("TenantsView", () => {
  beforeEach(() => {
    vi.mocked(listTenants).mockResolvedValue(tenantPage);
    vi.mocked(createTenant).mockResolvedValue(createdTenant);
    vi.mocked(updateTenant).mockResolvedValue(tenant);
    vi.mocked(enterTenant).mockResolvedValue(tenant);
    applicationContext.refreshContext.mockResolvedValue(null);
  });

  it("enters the selected Tenant from its name and opens the overview", async () => {
    renderView();

    fireEvent.click(
      await screen.findByRole("button", { name: "Accedi a Acme Italia" }),
    );

    await waitFor(() => {
      expect(enterTenant).toHaveBeenCalledWith(17);
      expect(applicationContext.refreshContext).toHaveBeenCalledOnce();
    });
    expect(await screen.findByText("Panoramica Tenant")).toBeInTheDocument();
  });

  it("keeps all six bootstrap fields in the create modal and normalizes VAT", async () => {
    renderView();

    fireEvent.click(await screen.findByRole("button", { name: "Nuovo Tenant" }));
    const dialog = screen.getByRole("dialog");

    const name = within(dialog).getByLabelText("Nome");
    const code = within(dialog).getByLabelText("Codice");
    const currency = within(dialog).getByLabelText("Valuta");
    const language = within(dialog).getByLabelText("Lingua");
    const timezone = within(dialog).getByLabelText("Fuso orario");
    const vat = within(dialog).getByLabelText("Aliquota IVA predefinita");

    expect([name, code, currency, language, timezone, vat]).toHaveLength(6);
    expect(
      within(dialog).queryByLabelText("Base Budget ufficiale"),
    ).not.toBeInTheDocument();

    fireEvent.change(name, { target: { value: "Nuovo Tenant" } });
    fireEvent.change(code, { target: { value: "nuovo-tenant" } });
    fireEvent.change(currency, { target: { value: "EUR" } });
    fireEvent.change(language, { target: { value: "it" } });
    fireEvent.change(timezone, { target: { value: "Europe/Rome" } });
    fireEvent.change(vat, { target: { value: "20,50" } });
    fireEvent.click(within(dialog).getByRole("button", { name: "Crea Tenant" }));

    await waitFor(() => {
      expect(createTenant).toHaveBeenCalledWith({
        name: "Nuovo Tenant",
        code: "nuovo-tenant",
        currency_code: "EUR",
        language_code: "it",
        timezone: "Europe/Rome",
        default_vat_rate: "20.50",
      });
    });
  });

  it("restricts edit to platform identity fields and sends a closed payload", async () => {
    renderView();

    fireEvent.click(
      await screen.findByRole("button", { name: "Modifica Acme Italia" }),
    );
    const dialog = screen.getByRole("dialog");

    expect(within(dialog).getByLabelText("Codice")).toHaveValue("acme-it");
    expect(within(dialog).getByLabelText("Valuta")).toHaveValue("EUR");
    expect(within(dialog).getByLabelText("Lingua")).toHaveValue("it");
    expect(within(dialog).queryByLabelText("Nome")).not.toBeInTheDocument();
    expect(
      within(dialog).queryByLabelText("Fuso orario"),
    ).not.toBeInTheDocument();
    expect(
      within(dialog).queryByLabelText("Aliquota IVA predefinita"),
    ).not.toBeInTheDocument();
    expect(
      within(dialog).queryByLabelText("Base Budget ufficiale"),
    ).not.toBeInTheDocument();

    fireEvent.change(within(dialog).getByLabelText("Codice"), {
      target: { value: "acme-eu" },
    });
    fireEvent.change(within(dialog).getByLabelText("Valuta"), {
      target: { value: "USD" },
    });
    fireEvent.change(within(dialog).getByLabelText("Lingua"), {
      target: { value: "en" },
    });
    fireEvent.click(
      within(dialog).getByRole("button", { name: "Salva Modifiche" }),
    );

    await waitFor(() => {
      expect(updateTenant).toHaveBeenCalledWith(17, {
        code: "acme-eu",
        currency_code: "USD",
        language_code: "en",
        lock_version: 4,
      });
    });
  });

  it("keeps operational settings out of the platform registry table", async () => {
    renderView();

    const table = await screen.findByRole("table");
    expect(within(table).getByText("Acme Italia")).toBeInTheDocument();
    expect(
      within(table).queryByRole("columnheader", { name: "Fuso orario" }),
    ).not.toBeInTheDocument();
    expect(
      within(table).queryByRole("columnheader", { name: "IVA predefinita" }),
    ).not.toBeInTheDocument();
  });
});
