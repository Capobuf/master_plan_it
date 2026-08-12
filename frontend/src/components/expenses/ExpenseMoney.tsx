import type { EconomicMeasure } from "../../api/projection";
import { formatMoney } from "../../presentation/formatters";

interface ExpenseMoneyProps {
  money: EconomicMeasure;
  component?: "net" | "vat" | "gross" | "official";
  currency?: string;
  className?: string;
}

export default function ExpenseMoney({
  money,
  component = "gross",
  currency = "EUR",
  className = "",
}: ExpenseMoneyProps) {
  const value = money[component];
  const formatted = formatMoney(value, currency);

  return <span className={className}>{formatted}</span>;
}
