import { fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { User } from "../../api/client";
import { routes } from "../../navigation/routes";
import SignInForm from "./SignInForm";

const auth = vi.hoisted(() => ({
  currentUser: null as User | null,
  authenticated: false,
  login: vi.fn(),
}));

vi.mock("../../context/AuthContext", () => ({
  useAuth: () => ({
    currentUser: auth.currentUser,
    authenticated: auth.authenticated,
    loading: false,
    login: auth.login,
  }),
}));

const platformAdministrator: User = {
  id: 1,
  name: "Amministratore",
  email: "admin@example.test",
  active: true,
  tenant_id: null,
};

const tenantUser: User = {
  id: 2,
  name: "Utente Tenant",
  email: "utente@example.test",
  active: true,
  tenant_id: 41,
};

function renderSignIn() {
  return render(
    <MemoryRouter initialEntries={[routes.accesso]}>
      <Routes>
        <Route path={routes.accesso} element={<SignInForm />} />
        <Route path={routes.tenant} element={<p>Lista Tenant</p>} />
        <Route path={routes.panoramica} element={<p>Panoramica</p>} />
      </Routes>
    </MemoryRouter>,
  );
}

async function submitCredentials(user: User) {
  auth.login.mockResolvedValueOnce(user);
  renderSignIn();

  fireEvent.change(screen.getByLabelText(/Email/), {
    target: { value: user.email },
  });
  fireEvent.change(screen.getByLabelText(/^Password/), {
    target: { value: "password" },
  });
  fireEvent.click(screen.getByRole("button", { name: "Accedi" }));
}

describe("SignInForm landing route", () => {
  beforeEach(() => {
    auth.currentUser = null;
    auth.authenticated = false;
  });

  it("opens the Tenant list for the platform administrator", async () => {
    await submitCredentials(platformAdministrator);

    expect(await screen.findByText("Lista Tenant")).toBeInTheDocument();
  });

  it("keeps Tenant users on the overview", async () => {
    await submitCredentials(tenantUser);

    expect(await screen.findByText("Panoramica")).toBeInTheDocument();
  });
});
