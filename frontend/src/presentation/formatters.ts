const DECIMAL_PATTERN = /^-?\d+(?:\.\d+)?$/;

function incrementDigits(value: string): string {
  const digits = value.split("");
  for (let index = digits.length - 1; index >= 0; index -= 1) {
    if (digits[index] !== "9") {
      digits[index] = String.fromCharCode(digits[index].charCodeAt(0) + 1);
      return digits.join("");
    }
    digits[index] = "0";
  }
  return `1${digits.join("")}`;
}

function decimalParts(value: string, scale: number) {
  const canonical = value.trim();
  if (!DECIMAL_PATTERN.test(canonical)) return null;
  const negative = canonical.startsWith("-");
  const unsigned = negative ? canonical.slice(1) : canonical;
  const [rawInteger, rawFraction = ""] = unsigned.split(".");
  const integer = rawInteger.replace(/^0+(?=\d)/, "") || "0";
  const padded = rawFraction.padEnd(scale + 1, "0");
  let combined = `${integer}${padded.slice(0, scale)}`;
  if ((padded[scale] ?? "0") >= "5") combined = incrementDigits(combined);
  const normalized = combined.padStart(scale + 1, "0");
  const integerPart = scale === 0 ? normalized : normalized.slice(0, -scale);
  const fractionPart = scale === 0 ? "" : normalized.slice(-scale);
  const isZero = /^0+$/.test(integerPart + fractionPart);
  return { negative: negative && !isZero, integerPart, fractionPart };
}

export function formatDecimal(value: string | null | undefined, scale = 2): string {
  if (value === null || value === undefined || value === "") return "—";
  const parts = decimalParts(value, scale);
  if (!parts) return value;
  const grouped = parts.integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  return `${parts.negative ? "−" : ""}${grouped}${scale > 0 ? `,${parts.fractionPart}` : ""}`;
}

export function formatMoney(
  value: string | null | undefined,
  currency = "EUR",
): string {
  const formatted = formatDecimal(value, 2);
  if (formatted === "—" || !DECIMAL_PATTERN.test((value ?? "").trim())) return formatted;
  return currency.toUpperCase() === "EUR" ? `${formatted} €` : `${formatted} ${currency.toUpperCase()}`;
}

export function formatPercentage(value: string | null | undefined): string {
  const formatted = formatDecimal(value, 2);
  return formatted === "—" ? formatted : `${formatted} %`;
}

export function formatEditableDecimal(
  value: string | null | undefined,
  options: { fixedScale?: number; trimTrailingZeros?: boolean } = {},
): string {
  if (value === null || value === undefined || value === "") return "";
  const canonical = value.trim().replace(",", ".");
  if (!DECIMAL_PATTERN.test(canonical)) return value;
  if (options.fixedScale !== undefined) return formatDecimal(canonical, options.fixedScale).replace(/\./g, "").replace(",", ".").replace("−", "-");
  if (!options.trimTrailingZeros || !canonical.includes(".")) return canonical;
  const [integer, fraction] = canonical.split(".");
  const trimmed = fraction.replace(/0+$/, "");
  return trimmed ? `${integer}.${trimmed}` : integer;
}

export function normalizeDecimalInput(value: string, maxScale = 6): string {
  const normalized = value.trim().replace(",", ".");
  if (!new RegExp(`^-?\\d+(?:\\.\\d{1,${maxScale}})?$`).test(normalized)) {
    throw new Error(`Inserisci un numero con al massimo ${maxScale} decimali.`);
  }
  return normalized;
}

export function compareDecimalStrings(left: string, right: string): number {
  const normalize = (value: string) => {
    const negative = value.startsWith("-");
    const unsigned = negative ? value.slice(1) : value;
    const [integer, fraction = ""] = unsigned.split(".");
    return { negative, integer: integer.replace(/^0+/, "") || "0", fraction: fraction.replace(/0+$/, "") };
  };
  const a = normalize(left);
  const b = normalize(right);
  if (a.negative !== b.negative) return a.negative ? -1 : 1;
  const direction = a.negative ? -1 : 1;
  if (a.integer.length !== b.integer.length) return (a.integer.length - b.integer.length) * direction;
  if (a.integer !== b.integer) return a.integer.localeCompare(b.integer) * direction;
  const width = Math.max(a.fraction.length, b.fraction.length);
  return a.fraction.padEnd(width, "0").localeCompare(b.fraction.padEnd(width, "0")) * direction;
}

export function isPositiveDecimal(value: string | null | undefined): boolean {
  return Boolean(value && DECIMAL_PATTERN.test(value) && !value.startsWith("-") && /[1-9]/.test(value));
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!match) return value;
  return `${match[3]}/${match[2]}/${match[1]}`;
}

export function formatDateTime(
  value: string | null | undefined,
  timezone?: string | null,
): string {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "short",
    timeStyle: "medium",
    ...(timezone ? { timeZone: timezone } : {}),
  }).format(date);
}

export function toChartNumber(value: string): number {
  return Number.parseFloat(value);
}
