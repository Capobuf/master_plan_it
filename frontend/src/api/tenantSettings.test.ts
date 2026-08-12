import { describe, expect, it } from "vitest";
import type { TenantSettings, TenantSettingsUpdate } from "./tenantSettings";

describe("tenant settings adapter", () => {
  it("exposes the public economic basis names rather than the storage name", () => {
    const input: TenantSettingsUpdate = { name: "Acme", timezone: "Europe/Rome", default_vat_rate: "22.00", economic_basis: "gross", deletion_reason_required: false, lock_version: 1 };
    const response: TenantSettings = { tenant_id: 1, name: input.name, currency_code: "EUR", timezone: input.timezone, default_vat_rate: input.default_vat_rate, economic_basis: input.economic_basis, economic_basis_locked_at: null, deletion_reason_required: false, lock_version: 2 };
    expect(response.economic_basis).toBe("gross");
    expect("budget_basis" in input).toBe(false);
  });
});
