import { useEffect, useRef, useState } from "react";
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
  readOnly?: boolean;
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
  readOnly,
  placeholder,
  error,
  hint,
  ariaLabel,
}: DecimalInputProps) {
  const formattedValue = formatEditableDecimal(value, { fixedScale, trimTrailingZeros });
  const [displayValue, setDisplayValue] = useState(formattedValue.replace(".", ","));
  const pendingInputValue = useRef<{ value: string } | null>(null);

  useEffect(() => {
    if (pendingInputValue.current?.value === value) {
      pendingInputValue.current = null;
      return;
    }

    pendingInputValue.current = null;
    setDisplayValue(formattedValue.replace(".", ","));
  }, [formattedValue, value]);

  return (
    <InputField
      id={id}
      type="text"
      inputMode="decimal"
      value={displayValue}
      onChange={(event) => {
        const nextValue = event.target.value;
        pendingInputValue.current = { value: nextValue };
        setDisplayValue(nextValue);
        onChange(nextValue);
      }}
      onBlur={() => {
        if (!displayValue.trim()) return;
        try {
          const normalized = normalizeDecimalInput(displayValue, maxScale);
          const next = formatEditableDecimal(normalized, { fixedScale, trimTrailingZeros });
          pendingInputValue.current = { value: next };
          setDisplayValue(next.replace(".", ","));
          onChange(next);
        } catch {
          // Validation remains visible in the owning form; never alter significant digits silently.
        }
      }}
      disabled={disabled}
      readOnly={readOnly}
      placeholder={placeholder}
      error={error}
      hint={hint}
      ariaLabel={ariaLabel}
    />
  );
}
