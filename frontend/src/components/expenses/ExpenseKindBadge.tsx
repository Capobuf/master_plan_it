import Badge from "../ui/badge/Badge";
import { domainLabel } from "../../presentation/labels";

export default function ExpenseKindBadge({ kind }: { kind: string }) {
  const normalized = kind.toLowerCase();
  const color = normalized === "plafond" ? "info" : "primary";

  return (
    <Badge size="sm" color={color}>
      {domainLabel(kind)}
    </Badge>
  );
}

export function ExpenseRowStateBadge({ state }: { state: string | null }) {
  const normalized = state?.toLowerCase();
  const label = domainLabel(state);
  const color = normalized === "confirmed" ? "success" : "warning";

  return (
    <Badge size="sm" color={color}>
      {label}
    </Badge>
  );
}
