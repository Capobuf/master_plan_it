import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { MemoryRouter } from "react-router";
import { listAbilities, listRoles } from "../../api/roles";
import RolesView from "./RolesView";

vi.mock("../../api/roles", () => ({
  listRoles: vi.fn(),
  listAbilities: vi.fn(),
  createRole: vi.fn(),
  updateRole: vi.fn(),
  deleteRole: vi.fn(),
}));

const page = {
  data: [{ id: 8, name: "Operations", abilities: ["tenant-settings.view", "tenant-users.manage"] }],
  meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 },
  links: { first: null, last: null, prev: null, next: null },
};

describe("RolesView", () => {
  it("shows readable permission detail without mutation controls in view-only mode", async () => {
    vi.mocked(listRoles).mockResolvedValue(page);
    render(<MemoryRouter><RolesView canView canManage={false} /></MemoryRouter>);

    expect(await screen.findByText("Operations")).toBeInTheDocument();
    expect(screen.getByText("Visualizzare le impostazioni del Tenant")).toBeInTheDocument();
    expect(screen.getByText("Gestire gli utenti del Tenant")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Nuovo Ruolo" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Modifica" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Elimina" })).not.toBeInTheDocument();
    expect(listAbilities).not.toHaveBeenCalled();
  });

  it("loads the closed ability catalogue and exposes mutations in manage mode", async () => {
    vi.mocked(listRoles).mockResolvedValue(page);
    vi.mocked(listAbilities).mockResolvedValue({
      data: [{
        name: "tenant-settings.view",
        label: "Visualizzare le impostazioni del Tenant",
      }],
      meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 },
      links: { first: null, last: null, prev: null, next: null },
    });
    render(<MemoryRouter><RolesView canView canManage /></MemoryRouter>);

    expect(await screen.findByRole("button", { name: "Nuovo Ruolo" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Modifica" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Elimina" })).toBeInTheDocument();
    expect(listAbilities).toHaveBeenCalled();
  });
});
