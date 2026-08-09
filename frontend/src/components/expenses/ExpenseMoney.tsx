import type { ExpenseMoney as ExpenseMoneyValue } from "../../api/expenses";
import { formatMoney } from "../../presentation/formatters";

interface ExpenseMoneyProps {
  money: ExpenseMoneyValue;
  component?: "net" | "vat" | "gross";
  className?: string;
}

export default function ExpenseMoney({
  money,
  component = "gross",
  className = "",
}: ExpenseMoneyProps) {
  const value = money[component];
  const formatted = formatMoney(value, money.currency);

  return <span className={className}>{formatted}</span>;
}
