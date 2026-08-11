import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { OperationalRevision, RevisionComparison } from "../../api/revisions";
import RevisionHistoryPanel from "./RevisionHistoryPanel";

const systemRevision: OperationalRevision = {
  id: 42,
  operation: "update",
  actor: { kind: "system", label: "Sistema" },
  timestamp: "2026-08-11T09:30:00Z",
  summary: "Spesa e 2 righe aggiornate",
  changed_count: 3,
  changed_fields: ["Titolo", "Righe della Spesa"],
  can_compare: true,
  can_restore: true,
};

const comparison: RevisionComparison = {
  revision: systemRevision,
  changes: [
    {
      scope: "expense",
      subject: "Spesa",
      field: "vendor_id",
      label: "Fornitore",
      revision_value: "Microsoft Italia",
      current_value: "Unidos S.r.l.",
    },
    {
      scope: "expense_row",
      subject: "Riga licenze",
      field: "entered_amount",
      label: "Importo inserito",
      revision_value: "1000.000000",
      current_value: "1250.00",
    },
    {
      scope: "expense_row",
      subject: "Riga licenze",
      field: "type",
      label: "Tipo riga",
      revision_value: "estimate",
      current_value: "actual",
    },
  ],
};

describe("RevisionHistoryPanel", () => {
  it("shows semantic history and confirms a permitted restore", async () => {
    const onCompare = vi.fn().mockResolvedValue(comparison);
    const onRestore = vi.fn().mockResolvedValue(undefined);

    render(<RevisionHistoryPanel revisions={[systemRevision]} loading={false} loadError={null} onReload={vi.fn()} onCompare={onCompare} onRestore={onRestore} />);

    expect(screen.getAllByText("Sistema").length).toBeGreaterThan(0);
    expect(screen.getByText("Spesa e 2 righe aggiornate")).toBeInTheDocument();
    expect(screen.getByText((_, element) => element?.tagName === "P" && element.textContent?.includes("3 elementi modificati") === true)).toBeInTheDocument();
    expect(screen.queryByText("42")).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Confronta con attuale" }));
    expect(await screen.findByText("Microsoft Italia")).toBeInTheDocument();
    expect(screen.getByText("Unidos S.r.l.")).toBeInTheDocument();
    expect(screen.getByText("1.000,00")).toBeInTheDocument();
    expect(screen.getByText("1.250,00")).toBeInTheDocument();
    expect(screen.getByText("Stima")).toBeInTheDocument();
    expect(screen.getByText("Consuntivo")).toBeInTheDocument();
    expect(screen.queryByText("1000.000000")).not.toBeInTheDocument();
    expect(onCompare).toHaveBeenCalledWith(42);

    const restoreButton = screen.getByRole("button", { name: "Ripristina" });
    await waitFor(() => expect(restoreButton).toBeEnabled());
    fireEvent.click(restoreButton);
    expect(screen.getByText("Confermare il ripristino?")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Conferma ripristino" }));

    await waitFor(() => expect(onRestore).toHaveBeenCalledWith(42));
  });

  it("does not offer restore without the server ability", async () => {
    const readOnlyComparison = { ...comparison, revision: { ...systemRevision, can_restore: false } };
    render(<RevisionHistoryPanel revisions={[readOnlyComparison.revision]} loading={false} loadError={null} onReload={vi.fn()} onCompare={vi.fn().mockResolvedValue(readOnlyComparison)} onRestore={vi.fn()} />);

    fireEvent.click(screen.getByRole("button", { name: "Confronta con attuale" }));
    await screen.findByText("Microsoft Italia");
    expect(screen.queryByRole("button", { name: "Ripristina" })).not.toBeInTheDocument();
  });
});
