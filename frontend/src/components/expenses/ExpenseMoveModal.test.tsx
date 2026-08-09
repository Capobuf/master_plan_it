import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { listExpensePlanningYears, moveExpense } from "../../api/expenses";
import ExpenseMoveModal from "./ExpenseMoveModal";

vi.mock("../../api/expenses", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/expenses")>();
  return {
    ...actual,
    listExpensePlanningYears: vi.fn(),
    moveExpense: vi.fn(),
  };
});

describe("ExpenseMoveModal", () => {
  it("auto-selects the only eligible year and returns the destination expense", async () => {
    vi.mocked(listExpensePlanningYears).mockResolvedValue([
      { id: 1, label: 2026, active: true },
      { id: 2, label: 2027, active: false },
      { id: 3, label: 2028, active: true },
    ]);
    vi.mocked(moveExpense).mockResolvedValue({ destination: { id: 88 } } as never);
    const onMoved = vi.fn();
    render(
      <ExpenseMoveModal
        expenseId={42}
        lockVersion={7}
        currentPlanningYearId={1}
        isOpen
        onClose={vi.fn()}
        onMoved={onMoved}
      />,
    );

    const select = await screen.findByLabelText("Anno di destinazione");
    await waitFor(() => expect(select).toHaveValue("3"));
    expect(screen.queryByRole("option", { name: "2026" })).not.toBeInTheDocument();
    expect(screen.queryByRole("option", { name: "2027" })).not.toBeInTheDocument();
    expect(screen.getByRole("option", { name: "2028" })).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Sposta spesa" }));

    await waitFor(() => {
      expect(moveExpense).toHaveBeenCalledWith(42, {
        lock_version: 7,
        target_planning_year_id: 3,
      });
      expect(onMoved).toHaveBeenCalledWith(88);
    });
  });
});
