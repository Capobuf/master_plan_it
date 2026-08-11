import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { getPlatformOverview } from "../../api/platform";
import PlatformOverviewView from "./PlatformOverviewView";

vi.mock("../../api/platform", () => ({ getPlatformOverview: vi.fn() }));

describe("PlatformOverviewView", () => {
  it("renders operational fields without cross-Tenant economics", async () => {
    vi.mocked(getPlatformOverview).mockResolvedValue([{
      tenant_id: 1,
      name: "Acme",
      state: "active",
      user_count: 4,
      active_user_count: 3,
      last_activity_at: null,
      upcoming_renewals: 2,
      operational_errors: [],
    }]);
    render(<PlatformOverviewView />);

    expect(screen.getByText("Caricamento overview")).toBeInTheDocument();
    expect(await screen.findByText("Acme")).toBeInTheDocument();
    expect(screen.getByText("Utenti attivi")).toBeInTheDocument();
    expect(screen.queryByText(/budget|spesa|gross|netto/i)).not.toBeInTheDocument();
  });

  it("renders an empty state", async () => {
    vi.mocked(getPlatformOverview).mockResolvedValue([]);
    render(<PlatformOverviewView />);

    expect(await screen.findByText("Nessun Tenant")).toBeInTheDocument();
  });
});
