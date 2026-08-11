import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { MemoryRouter } from "react-router";
import { listUserRoleOptions, listUsers } from "../../api/users";
import UsersView from "./UsersView";

vi.mock("../../api/users", () => ({
  listUsers: vi.fn(),
  listUserRoleOptions: vi.fn(),
  createUser: vi.fn(),
  updateUser: vi.fn(),
  assignUserRoles: vi.fn(),
  deactivateUser: vi.fn(),
  resetUserPassword: vi.fn(),
}));

const page = {
  data: [{ id: 7, name: "Mario Rossi", email: "mario@example.test", active: true, roles: [{ id: 2, name: "Viewer" }], role_ids: [2] }],
  meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 },
  links: { first: null, last: null, prev: null, next: null },
};

describe("UsersView", () => {
  it("keeps list, search, role and state visible without mutation controls in view-only mode", async () => {
    vi.mocked(listUsers).mockResolvedValue(page);
    render(<MemoryRouter><UsersView canView canManage={false} /></MemoryRouter>);

    expect(await screen.findByText("Mario Rossi")).toBeInTheDocument();
    expect(screen.getByText("Lettore")).toBeInTheDocument();
    expect(screen.getByText("Attivo")).toBeInTheDocument();
    expect(screen.getByPlaceholderText("Nome o email")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Nuovo Utente" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Modifica" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Disattiva" })).not.toBeInTheDocument();
    expect(listUserRoleOptions).not.toHaveBeenCalled();
  });

  it("renders the empty state", async () => {
    vi.mocked(listUsers).mockResolvedValue({ ...page, data: [], meta: { ...page.meta, total: 0 } });
    render(<MemoryRouter><UsersView canView canManage={false} /></MemoryRouter>);

    expect(await screen.findByText("Nessun utente")).toBeInTheDocument();
  });

  it("loads assignable role options and exposes supported mutations in manage mode", async () => {
    vi.mocked(listUsers).mockResolvedValue(page);
    vi.mocked(listUserRoleOptions).mockResolvedValue({
      data: [{ id: 2, name: "Viewer" }],
      meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 },
      links: { first: null, last: null, prev: null, next: null },
    });
    render(<MemoryRouter><UsersView canView canManage /></MemoryRouter>);

    expect(await screen.findByRole("button", { name: "Nuovo Utente" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Modifica" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Disattiva" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Password" })).toBeInTheDocument();
    expect(listUserRoleOptions).toHaveBeenCalled();
  });
});
