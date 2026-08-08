import type { ExpenseMoney as ExpenseMoneyValue } from "../../api/expenses";

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
  const formatted = new Intl.NumberFormat(undefined, {
    style: "currency",
    currency: money.currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value));

  return <span className={className}>{formatted}</span>;
}
