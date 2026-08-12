import type { ExpenseRowInput } from "../../api/expenses";

export type ExpenseEditorRow = ExpenseRowInput & { editorKey: string };

function editorKey(): string {
  return globalThis.crypto?.randomUUID?.() ?? `new-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function newExpenseEditorRow(position: number): ExpenseEditorRow {
  return {
    editorKey: `new-${editorKey()}`,
    position,
    type: "estimate",
    description: "",
    entered_amount: "0.00",
    amount_includes_vat: false,
  };
}

export function normalizeDecimal(value: string | null | undefined): string {
  if (value === null || value === undefined || value.trim() === "") {
    return "";
  }

  const canonical = value.trim().replace(",", ".");
  const negative = canonical.startsWith("-");
  const unsigned = canonical.replace(/^-/, "");
  const [rawInteger, rawFraction = ""] = unsigned.split(".", 2);
  const integer = rawInteger.replace(/^0+(?=\d)/, "") || "0";
  const fraction = rawFraction.replace(/0+$/, "");
  const normalized = fraction ? `${integer}.${fraction}` : integer;

  return negative && normalized !== "0" ? `-${normalized}` : normalized;
}

export function isDecimalText(value: string): boolean {
  return /^-?\d+(?:\.\d{1,2})?$/.test(value);
}
