import Badge from "../ui/badge/Badge";

const labels: Record<string, string> = {
  ordinary: "Ordinaria",
  plafond: "Plafond",
  estimate: "Stima",
  quote: "Preventivo",
  actual: "Effettiva",
};

function expenseKindLabel(kind: string): string {
  return labels[kind.toLowerCase()] ?? kind;
}

export default function ExpenseKindBadge({ kind }: { kind: string }) {
  const normalized = kind.toLowerCase();
  const color = normalized === "plafond" ? "info" : "primary";

  return (
    <Badge size="sm" color={color}>
      {expenseKindLabel(kind)}
    </Badge>
  );
}

export function ExpenseRowStateBadge({ state }: { state: string | null }) {
  const normalized = state?.toLowerCase();
  const label =
    normalized === "to_confirm"
      ? "Da confermare"
      : normalized === "confirmed"
        ? "Confermata"
        : state ?? "—";
  const color = normalized === "confirmed" ? "success" : "warning";

  return (
    <Badge size="sm" color={color}>
      {label}
    </Badge>
  );
}
