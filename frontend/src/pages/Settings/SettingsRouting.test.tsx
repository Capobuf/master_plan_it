import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { MemoryRouter, Route, Routes } from "react-router";
import SettingsLayout from "./Layout";

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    hasAbility: (ability: string) => ["tenant-users.view", "cost-center.view"].includes(ability),
  }),
}));

describe("SettingsLayout", () => {
  it("hides unauthorized tabs and keeps the tab bar horizontally scrollable", () => {
    render(
      <MemoryRouter initialEntries={["/impostazioni/utenti"]}>
        <Routes>
          <Route path="/impostazioni" element={<SettingsLayout />}>
            <Route path="utenti" element={<p>Elenco utenti</p>} />
          </Route>
        </Routes>
      </MemoryRouter>,
    );

    expect(screen.getByRole("link", { name: "Utenti" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Centri di costo" })).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Generali" })).not.toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Ruoli e Permessi" })).not.toBeInTheDocument();
    expect(screen.getByRole("navigation", { name: "Sezioni Impostazioni" })).toHaveClass("overflow-x-auto");
  });
});
