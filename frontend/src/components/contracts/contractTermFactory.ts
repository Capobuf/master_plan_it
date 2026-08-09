import type { ContractTermInput } from "../../api/contracts";

export function newContractTerm(): ContractTermInput {
  return {
    local_key: createLocalKey(),
    effective_start: "",
    effective_end: "",
    billing_cycle: "monthly",
    quantity: null,
    unit_price: null,
    entered_amount: "0.00",
    amount_includes_vat: false,
    vat_rate: "0.00",
    auto_renew: false,
  };
}

function createLocalKey(): string {
  if (typeof globalThis.crypto.randomUUID === "function") {
    return globalThis.crypto.randomUUID();
  }

  const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const value = Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
  return `${value.slice(0, 8)}-${value.slice(8, 12)}-${value.slice(12, 16)}-${value.slice(16, 20)}-${value.slice(20)}`;
}
