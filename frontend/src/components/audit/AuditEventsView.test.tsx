import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { listAuditEvents } from "../../api/audit";
import AuditEventsView from "./AuditEventsView";

vi.mock("../../api/audit", () => ({ listAuditEvents: vi.fn() }));

const emptyPage = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 50, total: 0 },
  links: { first: null, last: null, prev: null, next: null },
};

describe("AuditEventsView", () => {
  it("uses the global endpoint mode and renders loading then empty state", async () => {
    vi.mocked(listAuditEvents).mockResolvedValue(emptyPage);
    render(<AuditEventsView global />);

    expect(screen.getByText("Caricamento audit")).toBeInTheDocument();
    expect(await screen.findByText("Nessun evento")).toBeInTheDocument();
    expect(listAuditEvents).toHaveBeenCalledWith(true, { per_page: 50 });
  });

  it("renders an observable error state", async () => {
    vi.mocked(listAuditEvents).mockRejectedValue(new Error("Audit non raggiungibile"));
    render(<AuditEventsView />);

    expect(await screen.findByText("Audit non disponibile")).toBeInTheDocument();
  });
});
