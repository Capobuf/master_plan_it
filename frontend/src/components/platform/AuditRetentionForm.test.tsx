import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { getPlatformSettings, updateAuditRetention } from "../../api/platform";
import AuditRetentionForm from "./AuditRetentionForm";

vi.mock("../../api/platform", () => ({
  getPlatformSettings: vi.fn(),
  updateAuditRetention: vi.fn(),
}));

describe("AuditRetentionForm", () => {
  it("shows loading and requires reinforced confirmation when retention is reduced", async () => {
    vi.mocked(getPlatformSettings).mockResolvedValue({
      audit_retention_months: 24,
      lock_version: 5,
    });
    vi.mocked(updateAuditRetention).mockResolvedValue({
      audit_retention_months: 12,
      lock_version: 6,
    });
    render(<AuditRetentionForm />);

    expect(screen.getByText("Caricamento retention")).toBeInTheDocument();
    const months = await screen.findByDisplayValue("24");
    fireEvent.change(months, { target: { value: "12" } });
    const confirmation = screen.getByPlaceholderText("RIDUCI AUDIT A 12 MESI");
    fireEvent.change(confirmation, { target: { value: "RIDUCI AUDIT A 12 MESI" } });
    fireEvent.click(screen.getByRole("button", { name: "Salva retention" }));

    await waitFor(() => {
      expect(updateAuditRetention).toHaveBeenCalledWith(
        12,
        5,
        "RIDUCI AUDIT A 12 MESI",
      );
    });
  });

  it("shows an observable loading failure", async () => {
    vi.mocked(getPlatformSettings).mockRejectedValue(new Error("Servizio non disponibile"));
    render(<AuditRetentionForm />);

    expect(await screen.findByText("Operazione non riuscita")).toBeInTheDocument();
  });
});
