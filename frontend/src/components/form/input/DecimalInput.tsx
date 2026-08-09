import { useEffect, useState } from "react";
import { formatEditableDecimal, normalizeDecimalInput } from "../../../presentation/formatters";
import InputField from "./InputField";

interface DecimalInputProps {
  id: string;
  value: string | null;
  onChange: (value: string) => void;
  fixedScale?: number;
  maxScale?: number;
  trimTrailingZeros?: boolean;
  disabled?: boolean;
  placeholder?: string;
  error?: boolean;
  hint?: string;
  ariaLabel?: string;
}

export default function DecimalInput({
  id,
  value,
  onChange,
  fixedScale,
  maxScale = 6,
  trimTrailingZeros = false,
  disabled,
  placeholder,
  error,
  hint,
  ariaLabel,
}: DecimalInputProps) {
  const formattedValue = formatEditableDecimal(value, { fixedScale, trimTrailingZeros });
  const [displayValue, setDisplayValue] = useState(formattedValue.replace(".", ","));

  useEffect(() => {
    setDisplayValue(formattedValue.replace(".", ","));
  }, [formattedValue]);

  return (
    <InputField
      id={id}
      type="text"
      inputMode="decimal"
      value={displayValue}
      onChange={(event) => {
        setDisplayValue(event.target.value);
        onChange(event.target.value);
      }}
      onBlur={() => {
        if (!displayValue.trim()) return;
        try {
          const normalized = normalizeDecimalInput(displayValue, maxScale);
          const next = formatEditableDecimal(normalized, { fixedScale, trimTrailingZeros });
          setDisplayValue(next.replace(".", ","));
          onChange(next);
        } catch {
          // Validation remains visible in the owning form; never alter significant digits silently.
        }
      }}
      disabled={disabled}
      placeholder={placeholder}
      error={error}
      hint={hint}
      ariaLabel={ariaLabel}
    />
  );
}
