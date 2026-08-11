import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { ExpenseDetail } from "../../api/expenses";
import ExpenseAttachmentsPanel from "./ExpenseAttachmentsPanel";

vi.mock("./AttachmentPanel", () => ({
  default: ({ parent, title }: { parent: unknown; title: string }) => <div data-testid="attachment-panel"><span>{title}</span><code>{JSON.stringify(parent)}</code></div>,
}));

describe("ExpenseAttachmentsPanel", () => {
  it("separates the Expense from each row using human descriptions", () => {
    const expense = {
      id: 42,
      rows: [
        { id: 51, position: 1, description: "Canone cloud" },
        { id: 52, position: 2, description: "Supporto applicativo" },
      ],
    } as ExpenseDetail;

    render(<ExpenseAttachmentsPanel expense={expense} />);
    expect(screen.getByText("Spesa")).toBeInTheDocument();
    expect(screen.getByText("Riga 1 · Canone cloud")).toBeInTheDocument();
    expect(screen.getByText("Riga 2 · Supporto applicativo")).toBeInTheDocument();
    expect(screen.getAllByTestId("attachment-panel")).toHaveLength(3);
    expect(screen.getByText(JSON.stringify({ kind: "expense-row", expenseId: 42, rowId: 51 }))).toBeInTheDocument();
  });
});
