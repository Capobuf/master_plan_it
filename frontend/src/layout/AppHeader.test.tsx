import { fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it, vi } from "vitest";

import AppHeader from "./AppHeader";

const allowedAbilities = new Set(["dashboard.view", "expense.view"]);
vi.mock("../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    hasAbility: (ability: string) => allowedAbilities.has(ability),
  }),
}));
vi.mock("../components/header/WorkspaceContextBar", () => ({
  default: () => <div>Contesto annuale</div>,
}));
vi.mock("../components/header/UserDropdown", () => ({
  default: () => <div>Utente</div>,
}));

describe("AppHeader", () => {
  it("offers an accessible ability-filtered navigation menu on compact viewports", () => {
    render(<MemoryRouter><AppHeader /></MemoryRouter>);

    const toggle = screen.getByRole("button", { name: "Menu" });
    const navigation = screen.getByRole("navigation", { name: "Destinazioni applicative" });
    expect(toggle).toHaveAttribute("aria-expanded", "false");
    expect(navigation).toHaveClass("hidden");

    fireEvent.click(toggle);

    expect(toggle).toHaveAttribute("aria-expanded", "true");
    expect(navigation).toHaveClass("flex");
    expect(screen.getByRole("link", { name: "Spese" })).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Budget" })).not.toBeInTheDocument();
  });
});
