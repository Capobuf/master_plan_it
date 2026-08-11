import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { ExpenseEditorRow } from "./expenseEditorTypes";
import ExpenseEditorRows from "./ExpenseEditorRows";

vi.mock("react-dnd", () => ({
  useDrag: () => [{}, (node: unknown) => node],
  useDrop: () => [{}, (node: unknown) => node],
}));

const estimate: ExpenseEditorRow = {
  editorKey: "estimate-row",
  position: 1,
  type: "estimate",
  description: "Stima",
  entered_amount: "100.00",
  amount_includes_vat: false,
  is_extra: false,
};

const props = {
  vendors: [],
  plafonds: [],
  onChange: vi.fn(),
  onMove: vi.fn(),
  onRemove: vi.fn(),
};

describe("ExpenseEditorRows", () => {
  it("shows current planning only for non-actual rows", () => {
    const { rerender } = render(<ExpenseEditorRows {...props} rows={[estimate]} />);

    expect(screen.getByRole("checkbox", { name: "Corrente" })).toBeInTheDocument();

    rerender(<ExpenseEditorRows {...props} rows={[{ ...estimate, type: "actual" }]} />);

    expect(screen.queryByRole("checkbox", { name: "Corrente" })).not.toBeInTheDocument();
  });

  it("keeps the only row and removes the selected row when multiple rows exist", () => {
    const onRemove = vi.fn();
    const { rerender } = render(<ExpenseEditorRows {...props} onRemove={onRemove} rows={[estimate]} />);

    expect(screen.getByRole("button", { name: "Rimuovi la riga 1" })).toBeDisabled();

    rerender(
      <ExpenseEditorRows
        {...props}
        onRemove={onRemove}
        rows={[estimate, { ...estimate, editorKey: "quote-row", position: 2, type: "quote" }]}
      />,
    );
    fireEvent.click(screen.getByRole("button", { name: "Rimuovi la riga 2" }));

    expect(screen.getByRole("button", { name: "Rimuovi la riga 1" })).toBeEnabled();
    expect(onRemove).toHaveBeenCalledWith(1);
  });

  it("shows three ERP rows together and keeps keyboard move and expandable details", () => {
    const onMove = vi.fn();
    const rows = [
      estimate,
      { ...estimate, editorKey: "quote-row", position: 2, type: "quote", description: "Preventivo" },
      { ...estimate, editorKey: "actual-row", position: 3, type: "actual", description: "Consuntivo" },
    ];
    render(<ExpenseEditorRows {...props} rows={rows} onMove={onMove} />);

    expect(screen.getAllByRole("group", { name: /Riga/ })).toHaveLength(3);
    expect(screen.getAllByRole("columnheader", { name: "Tipo" })).toHaveLength(1);
    expect(screen.getAllByRole("columnheader", { name: "Fornitore" })).toHaveLength(1);
    expect(screen.getAllByRole("columnheader", { name: "Q.tà" })).toHaveLength(1);
    expect(screen.getAllByRole("columnheader", { name: "Prezzo unit." })).toHaveLength(1);
    expect(screen.getAllByRole("columnheader", { name: "Importo" })).toHaveLength(1);
    expect(screen.getAllByRole("columnheader", { name: "IVA" })).toHaveLength(1);
    expect(screen.getAllByRole("columnheader", { name: "Data" })).toHaveLength(1);
    expect(screen.queryByRole("textbox", { name: "Descrizione" })).not.toBeInTheDocument();

    const details = screen.getByRole("button", { name: "Mostra dettagli riga 2" });
    fireEvent.click(details);
    expect(screen.getByRole("button", { name: "Nascondi dettagli riga 2" })).toHaveAttribute("aria-expanded", "true");
    expect(screen.getByRole("textbox", { name: "Descrizione" })).toHaveValue("Preventivo");
    expect(screen.getByRole("checkbox", { name: "IVA inclusa" })).toBeInTheDocument();
    expect(screen.getByRole("checkbox", { name: "Spesa Extra" })).toBeInTheDocument();
    expect(screen.getByRole("combobox", { name: "Plafond di riferimento" })).toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: "Riferimento esterno" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Sposta la riga 2 in alto" }));
    expect(onMove).toHaveBeenCalledWith(1, 0);
  });
});
