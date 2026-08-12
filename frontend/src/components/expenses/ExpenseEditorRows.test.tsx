import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { ExpenseEditorRow } from "./expenseEditorTypes";
import ExpenseEditorRows from "./ExpenseEditorRows";

function relativeLuminance(hex: string): number {
  const channels = hex.match(/[a-f\d]{2}/gi)?.map((channel) => {
    const value = Number.parseInt(channel, 16) / 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  }) ?? [];

  return 0.2126 * (channels[0] ?? 0) + 0.7152 * (channels[1] ?? 0) + 0.0722 * (channels[2] ?? 0);
}

function contrastRatio(foreground: string, background: string): number {
  const lighter = Math.max(relativeLuminance(foreground), relativeLuminance(background));
  const darker = Math.min(relativeLuminance(foreground), relativeLuminance(background));
  return (lighter + 0.05) / (darker + 0.05);
}

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
};

const props = {
  vendors: [],
  onChange: vi.fn(),
  onMove: vi.fn(),
  onRemove: vi.fn(),
};

describe("ExpenseEditorRows", () => {
  it("shows the Tenant default only for new rows without an explicit VAT", () => {
    const { rerender } = render(
      <ExpenseEditorRows {...props} defaultVatRate="22.00" rows={[estimate]} />,
    );

    expect(screen.getByRole("textbox", { name: "IVA riga 1", hidden: true })).toHaveValue("22,00");

    rerender(
      <ExpenseEditorRows
        {...props}
        defaultVatRate="22.00"
        rows={[{ ...estimate, id: 7, vat_rate: "10.00" }]}
      />,
    );
    expect(screen.getByRole("textbox", { name: "IVA riga 1", hidden: true })).toHaveValue("10,00");

    rerender(
      <ExpenseEditorRows
        {...props}
        defaultVatRate="22.00"
        rows={[{ ...estimate, vat_rate: "0.00" }]}
      />,
    );
    expect(screen.getByRole("textbox", { name: "IVA riga 1", hidden: true })).toHaveValue("0,00");
  });

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

  it("shows three ERP rows together with drag handles and expandable details", () => {
    const rows = [
      estimate,
      { ...estimate, editorKey: "quote-row", position: 2, type: "quote", description: "Preventivo" },
      { ...estimate, editorKey: "actual-row", position: 3, type: "actual", description: "Consuntivo" },
    ];
    render(<ExpenseEditorRows {...props} rows={rows} />);

    expect(screen.getAllByRole("group", { name: /Riga/ })).toHaveLength(3);
    const details = screen.getByRole("button", { name: "Mostra dettagli riga 2" });
    fireEvent.click(details);
    expect(screen.getByRole("button", { name: "Nascondi dettagli riga 2" })).toHaveAttribute("aria-expanded", "true");
    expect(screen.queryByRole("button", { name: /Sposta la riga/ })).not.toBeInTheDocument();
    expect(screen.getAllByTitle("Trascina per riordinare")).toHaveLength(3);
  });

  it("keeps icon buttons readable in light and dark themes without visible duplicate labels", () => {
    render(<ExpenseEditorRows {...props} rows={[estimate]} />);

    const button = screen.getByRole("button", { name: "Mostra dettagli riga 1" });
    expect(button).toHaveClass("bg-white", "text-gray-600", "dark:bg-gray-900", "dark:text-gray-300");
    expect(button).toHaveAttribute("title", "Mostra dettagli riga 1");
    expect(screen.queryByText("Dettagli")).not.toBeInTheDocument();
    expect(contrastRatio("#475467", "#ffffff")).toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio("#d0d5dd", "#101828")).toBeGreaterThanOrEqual(4.5);
  });

  it("uses the localized calendar and keeps the main economic fields in order", () => {
    render(<ExpenseEditorRows {...props} rows={[estimate]} />);

    fireEvent.click(screen.getByRole("button", { name: "Apri calendario: Data riga 1" }));
    expect(document.querySelector(".flatpickr-calendar")).toHaveClass("open");

    const headings = Array.from(document.querySelectorAll("[data-erp-column-heading]")).map((heading) => heading.textContent);
    expect(headings.slice(4, 9)).toEqual(["Copertura Plafond", "Q.tà", "Prezzo Unitario", "Importo", "IVA inclusa"]);
    expect(screen.getByRole("checkbox", { name: "IVA inclusa" })).toBeInTheDocument();
    const vatInput = screen.getByRole("textbox", { name: "IVA riga 1", hidden: true });
    expect(vatInput.closest("section")).toHaveClass("hidden");

    fireEvent.click(screen.getByRole("button", { name: "Mostra dettagli riga 1" }));
    expect(screen.getByRole("textbox", { name: "IVA riga 1" })).toBeInTheDocument();
    expect(vatInput.closest("section")).not.toHaveClass("hidden");
  });

  it("opens hidden details and exposes accessible suggestions for API field errors", () => {
    render(
      <ExpenseEditorRows
        {...props}
        rows={[estimate]}
        validationErrors={{ "rows.0.unit_price": "Inserisci un numero valido con massimo 2 decimali." }}
      />,
    );

    expect(screen.getByRole("button", { name: "Mostra dettagli riga 1" })).toHaveAttribute("aria-expanded", "false");
    const unitPrice = screen.getByRole("textbox", { name: "Prezzo Unitario riga 1" });
    expect(unitPrice).toHaveAttribute("aria-invalid", "true");
    expect(unitPrice).toHaveAccessibleDescription("Inserisci un numero valido con massimo 2 decimali.");
  });
});
