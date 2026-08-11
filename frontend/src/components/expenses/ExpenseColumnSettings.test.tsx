import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { updateExpenseRegisterPreferences, type ExpenseColumnPreference } from "../../api/expenses";
import ExpenseColumnSettings from "./ExpenseColumnSettings";

vi.mock("../../api/expenses", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/expenses")>();
  return { ...actual, updateExpenseRegisterPreferences: vi.fn() };
});

const columns: ExpenseColumnPreference[] = [
  { key: "net", visible: true },
  { key: "vat", visible: true },
  { key: "gross", visible: true },
];

describe("ExpenseColumnSettings", () => {
  it("uses an icon-only trigger and persists column changes", async () => {
    const onChange = vi.fn();
    const nextColumns = columns.map((column) => column.key === "vat" ? { ...column, visible: false } : column);
    vi.mocked(updateExpenseRegisterPreferences).mockResolvedValue(nextColumns);

    render(<ExpenseColumnSettings value={columns} onChange={onChange} />);

    const trigger = screen.getByRole("button", { name: "Colonne" });
    expect(trigger).toHaveAttribute("title", "Configura le colonne");
    expect(screen.queryByText("Colonne")).not.toBeInTheDocument();

    fireEvent.click(trigger);
    fireEvent.click(screen.getByRole("checkbox", { name: "Mostra IVA" }));

    await waitFor(() => {
      expect(updateExpenseRegisterPreferences).toHaveBeenCalledWith(nextColumns);
      expect(onChange).toHaveBeenCalledWith(nextColumns);
    });
  });
});
