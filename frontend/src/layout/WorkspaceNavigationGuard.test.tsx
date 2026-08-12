import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useEffect } from "react";
import { createMemoryRouter, RouterProvider } from "react-router";
import { describe, expect, it, vi } from "vitest";

import PlanningYearDropdown from "../components/header/PlanningYearDropdown";
import {
  PlanningYearProvider,
  usePlanningYear,
} from "../context/PlanningYearContext";
import AppLayout from "./AppLayout";

const get = vi.hoisted(() => vi.fn());
vi.mock("../api/client", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../api/client")>();
  return { ...actual, apiClient: { get } };
});
vi.mock("./AppHeader", () => ({ default: () => <PlanningYearDropdown /> }));

function DirtyExpenseEditor() {
  const { registerDirtySource } = usePlanningYear();

  useEffect(() => {
    registerDirtySource("integration-editor", true);
    return () => registerDirtySource("integration-editor", false);
  }, [registerDirtySource]);

  return <p>Editor con modifiche</p>;
}

describe("workspace navigation guard", () => {
  it("accepts a dirty year change once and reaches the Expense Register", async () => {
    get.mockResolvedValue({
      data: {
        data: [
          { id: 1, year_label: 2026, start_date: "2026-01-01", end_date: "2026-12-31", active: true, lock_version: 1 },
          { id: 2, year_label: 2027, start_date: "2027-01-01", end_date: "2027-12-31", active: true, lock_version: 1 },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 100, total: 2 },
      },
    });
    const confirm = vi.spyOn(window, "confirm").mockReturnValue(true);
    const router = createMemoryRouter([{
      path: "/",
      element: <PlanningYearProvider tenantId={1} canRead><AppLayout /></PlanningYearProvider>,
      children: [
        { path: "spese/81/modifica", element: <DirtyExpenseEditor /> },
        { path: "spese", element: <p>Registro raggiunto</p> },
      ],
    }], { initialEntries: ["/spese/81/modifica"] });
    render(<RouterProvider router={router} />);

    await screen.findByRole("button", { name: /Anno di pianificazione: 2026/ });
    fireEvent.click(screen.getByRole("button", { name: /Anno di pianificazione/ }));
    fireEvent.click(screen.getByRole("button", { name: "2027" }));

    expect(await screen.findByText("Registro raggiunto")).toBeInTheDocument();
    await waitFor(() => expect(confirm).toHaveBeenCalledTimes(1));
    confirm.mockRestore();
  });
});
