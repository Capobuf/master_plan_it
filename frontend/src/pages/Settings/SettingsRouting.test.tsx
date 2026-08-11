import { render, screen, waitFor } from "@testing-library/react";
import type { PropsWithChildren } from "react";
import {
  MemoryRouter,
  Outlet,
  Route,
  Routes,
} from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";

import App from "../../App";
import { legacyRoutes, routes } from "../../navigation/routes";
import SettingsIndex from "./Index";
import SettingsLayout from "./Layout";

const context = vi.hoisted(() => ({
  abilities: [] as string[],
  loading: false,
}));

vi.mock("../../context/ApplicationContext", () => ({
  ApplicationProvider: ({ children }: PropsWithChildren) => <>{children}</>,
  useApplicationContext: () => ({
    data: { tenant: { id: 41 } },
    loading: context.loading,
    hasAbility: (ability: string) => context.abilities.includes(ability),
  }),
}));

vi.mock("../../context/AuthContext", () => ({
  AuthProvider: ({ children }: PropsWithChildren) => <>{children}</>,
}));

vi.mock("../../components/auth/ProtectedRoute", () => ({
  default: () => <Outlet />,
}));

vi.mock("../../layout/AppLayout", () => ({
  default: () => <Outlet />,
}));

vi.mock("../../components/common/ScrollToTop", () => ({
  ScrollToTop: () => null,
}));

vi.mock("../Users/Home", () => ({
  default: () => <p>Contenuto Utenti</p>,
}));

vi.mock("../Roles/Home", () => ({
  default: () => <p>Contenuto Ruoli</p>,
}));

vi.mock("../PlanningYears/Home", () => ({
  default: () => <p>Contenuto Anni</p>,
}));

vi.mock("../CostCenters/Home", () => ({
  default: () => <p>Contenuto Centri</p>,
}));

const childContent = {
  generali: "Contenuto Generali",
  utenti: "Contenuto Utenti",
  ruoli: "Contenuto Ruoli",
  "anni-di-pianificazione": "Contenuto Anni",
  "centri-di-costo": "Contenuto Centri",
} as const;

function renderWorkspace(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/impostazioni" element={<SettingsLayout />}>
          <Route index element={<SettingsIndex />} />
          {Object.entries(childContent).map(([pathSegment, content]) => (
            <Route
              key={pathSegment}
              path={pathSegment}
              element={<p>{content}</p>}
            />
          ))}
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe("Settings workspace routing", () => {
  beforeEach(() => {
    context.abilities = [];
    context.loading = false;
    window.history.pushState({}, "", "/");
  });

  it("shows authorized tabs in canonical order and marks the active tab", () => {
    context.abilities = [
      "tenant-settings.view",
      "platform.roles.manage",
      "cost-center.view",
    ];

    renderWorkspace(routes.impostazioniRuoli);

    const navigation = screen.getByRole("navigation", {
      name: "Sezioni Impostazioni",
    });
    expect(navigation).toHaveClass("overflow-x-auto");
    expect(
      screen.getAllByRole("link").map((link) => link.textContent),
    ).toEqual(["Generali", "Ruoli e permessi", "Centri di costo"]);
    expect(
      screen.getByRole("link", { name: "Ruoli e permessi" }),
    ).toHaveAttribute("aria-current", "page");
    expect(screen.getByText("Contenuto Ruoli")).toBeInTheDocument();
  });

  it("shows a loading state before resolving the workspace index", () => {
    context.loading = true;

    renderWorkspace(routes.impostazioni);

    expect(screen.getByText(/Caricamento delle impostazioni/i)).toBeInTheDocument();
    expect(screen.queryByText(/Contenuto/)).not.toBeInTheDocument();
  });

  it("redirects the workspace index to the first accessible section", async () => {
    context.abilities = ["cost-center.view", "platform.users.manage"];

    renderWorkspace(routes.impostazioni);

    expect(await screen.findByText("Contenuto Utenti")).toBeInTheDocument();
  });

  it("shows a diagnostic denial without redirecting when no section is accessible", () => {
    renderWorkspace(routes.impostazioni);

    expect(screen.getByText("Impostazioni non disponibili")).toBeInTheDocument();
    expect(screen.queryByText(/Contenuto/)).not.toBeInTheDocument();
  });

  it("denies an unauthorized deep link before mounting its child", () => {
    context.abilities = ["tenant-settings.view"];

    renderWorkspace(routes.impostazioniRuoli);

    expect(screen.getByText("Sezione non disponibile")).toBeInTheDocument();
    expect(screen.queryByText("Contenuto Ruoli")).not.toBeInTheDocument();
  });
});

describe("Settings legacy redirects", () => {
  beforeEach(() => {
    context.abilities = [
      "tenant-settings.view",
      "platform.users.manage",
      "platform.roles.manage",
      "planning-year.view",
      "cost-center.view",
    ];
  });

  it.each([
    [routes.utenti, routes.impostazioniUtenti],
    [legacyRoutes.utenti, routes.impostazioniUtenti],
    [routes.ruoli, routes.impostazioniRuoli],
    [legacyRoutes.ruoli, routes.impostazioniRuoli],
    [routes.anniDiPianificazione, routes.impostazioniAnni],
    [legacyRoutes.anniDiPianificazione, routes.impostazioniAnni],
    [routes.centriDiCosto, routes.impostazioniCentri],
    [legacyRoutes.centriDiCosto, routes.impostazioniCentri],
  ])("redirects %s directly to %s", async (source, destination) => {
    window.history.pushState({}, "", source);

    render(<App />);

    await waitFor(() => expect(window.location.pathname).toBe(destination));
  });
});
