import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ThemeProvider } from "../../context/ThemeContext";
import UserDropdown from "./UserDropdown";

vi.mock("../../context/AuthContext", () => ({
  useAuth: () => ({
    currentUser: { name: "Mario Rossi", email: "mario@example.test" },
    logout: vi.fn(),
  }),
}));

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    data: { platformAdministrator: true, tenant: null },
  }),
}));

describe("UserDropdown", () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove("dark");
  });

  it("lets the user choose the appearance from the profile menu", async () => {
    render(
      <MemoryRouter>
        <ThemeProvider>
          <UserDropdown />
        </ThemeProvider>
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole("button", { name: "Apri il menu utente" }));

    const lightTheme = screen.getByRole("radio", { name: "Chiaro" });
    const darkTheme = screen.getByRole("radio", { name: "Scuro" });

    expect(lightTheme).toBeChecked();
    expect(darkTheme).not.toBeChecked();

    fireEvent.click(darkTheme);

    await waitFor(() => {
      expect(darkTheme).toBeChecked();
      expect(document.documentElement).toHaveClass("dark");
      expect(localStorage.getItem("theme")).toBe("dark");
    });
  });
});
