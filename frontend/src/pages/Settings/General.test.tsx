import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";

import TenantGeneralSettingsPage from "./General";

const context = vi.hoisted(() => ({
  tenantId: 41 as number | null,
  loading: false,
  abilities: [] as string[],
}));

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    data: context.tenantId === null ? { tenant: null } : { tenant: { id: context.tenantId } },
    loading: context.loading,
    hasAbility: (ability: string) => context.abilities.includes(ability),
  }),
}));

vi.mock("../../components/settings/TenantGeneralSettingsForm", () => ({
  default: ({ canUpdate }: { canUpdate: boolean }) => (
    <p>Form impostazioni {canUpdate ? "modificabile" : "sola lettura"}</p>
  ),
}));

describe("TenantGeneralSettingsPage", () => {
  beforeEach(() => {
    context.tenantId = 41;
    context.loading = false;
    context.abilities = [];
  });

  it("renders the form in read-only mode with view ability", () => {
    context.abilities = ["tenant-settings.view"];

    render(<MemoryRouter><TenantGeneralSettingsPage /></MemoryRouter>);

    expect(screen.getByText("Form impostazioni sola lettura")).toBeInTheDocument();
  });

  it("allows update ability to load and modify the form", () => {
    context.abilities = ["tenant-settings.update"];

    render(<MemoryRouter><TenantGeneralSettingsPage /></MemoryRouter>);

    expect(screen.getByText("Form impostazioni modificabile")).toBeInTheDocument();
  });

  it("requires a selected Tenant before rendering settings", () => {
    context.tenantId = null;
    context.abilities = ["tenant-settings.view"];

    render(<MemoryRouter><TenantGeneralSettingsPage /></MemoryRouter>);

    expect(screen.getByText("Tenant richiesto")).toBeInTheDocument();
    expect(screen.queryByText(/Form impostazioni/)).not.toBeInTheDocument();
  });

  it("denies the page without view or update", () => {
    render(<MemoryRouter><TenantGeneralSettingsPage /></MemoryRouter>);

    expect(screen.getByText("Impostazioni non disponibili")).toBeInTheDocument();
  });
});
