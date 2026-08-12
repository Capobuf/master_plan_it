import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "../../api/client";
import * as plafondApi from "../../api/plafonds";
import PlafondsHome from "./Home";

const hasAbility = vi.fn(() => true);
vi.mock("../../context/ApplicationContext", () => ({ useApplicationContext: () => ({ hasAbility }) }));
vi.mock("../../context/PlanningYearContext", () => ({ usePlanningYear: () => ({ selectedPlanningYearId: 25 }) }));
vi.mock("../../api/plafonds", () => ({ listPlafonds: vi.fn() }));

describe("PlafondsHome", () => {
  beforeEach(() => { vi.clearAllMocks(); hasAbility.mockReturnValue(true); });

  it("shows the server-backed empty state", async () => {
    vi.mocked(plafondApi.listPlafonds).mockResolvedValue({ data: [], meta: {} as never, links: {} as never, currency: "EUR", basis: "net" });
    render(<MemoryRouter><PlafondsHome /></MemoryRouter>);
    expect(await screen.findByText("Nessun Plafond")).toBeInTheDocument();
    expect(plafondApi.listPlafonds).toHaveBeenCalledWith({ planning_year_id: 25, per_page: 100 });
  });

  it("keeps a diagnostic error and denies an unauthorized reader", async () => {
    vi.mocked(plafondApi.listPlafonds).mockRejectedValue(new ApiError({ message: "Servizio non disponibile", status: 500 }));
    const { rerender } = render(<MemoryRouter><PlafondsHome /></MemoryRouter>);
    expect(await screen.findByText("Richiesta non riuscita")).toBeInTheDocument();
    hasAbility.mockReturnValue(false);
    rerender(<MemoryRouter><PlafondsHome /></MemoryRouter>);
    expect(screen.getByText("Registro non disponibile")).toBeInTheDocument();
  });
});
