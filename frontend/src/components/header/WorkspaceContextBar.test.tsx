import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import WorkspaceContextBar from "./WorkspaceContextBar";

vi.mock("./TenantDropdown", () => ({ default: () => <span>Tenant corrente</span> }));
vi.mock("./PlanningYearDropdown", () => ({ default: () => <span>Anno economico</span> }));

describe("WorkspaceContextBar", () => {
  it("groups the Tenant and annual selectors under one labelled workspace context", () => {
    render(<WorkspaceContextBar />);
    expect(screen.getByRole("region", { name: "Contesto di lavoro" })).toHaveTextContent("Tenant corrente");
    expect(screen.getByRole("region", { name: "Contesto di lavoro" })).toHaveTextContent("Anno economico");
  });
});
