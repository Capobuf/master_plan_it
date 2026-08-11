import { fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router";
import { describe, expect, it, vi } from "vitest";
import { getContract, type Contract } from "../api/contracts";
import { getProject, type Project } from "../api/projects";
import ContractDetail from "./Contracts/ContractDetail";
import ProjectDetail from "./Projects/ProjectDetail";

vi.mock("../context/ApplicationContext", () => ({
  useApplicationContext: () => ({
    data: { tenant: { id: 1 }, abilities: [] },
    loading: false,
    hasAbility: () => true,
  }),
}));
vi.mock("../components/attachments/AttachmentPanel", () => ({
  default: ({ parent }: { parent: { kind: string; contractId?: number; projectId?: number } }) => (
    <div data-testid="attachment-panel">{parent.kind}:{parent.contractId ?? parent.projectId}</div>
  ),
}));
vi.mock("../api/contracts", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../api/contracts")>();
  return { ...actual, getContract: vi.fn() };
});
vi.mock("../api/projects", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../api/projects")>();
  return { ...actual, getProject: vi.fn() };
});

const contract: Contract = {
  id: 12,
  vendor_id: 2,
  vendor: { id: 2, name: "Fornitore demo" },
  cost_center_id: 3,
  cost_center: { id: 3, name: "Operations" },
  project_id: null,
  project: null,
  title: "Contratto demo",
  description: null,
  active: true,
  renewal_date: null,
  renewal_notice_days: null,
  renewal_notes: null,
  currency: "EUR",
  official_basis: "net",
  term_count: 0,
  generated_expense_count: 0,
  lock_version: 1,
  terms: [],
  occurrences: [],
  generated_expenses: [],
  revision_activity: [],
};

const project: Project = {
  id: 23,
  title: "Progetto demo",
  stage: "approved",
  cost_center_id: 3,
  cost_center: { id: 3, name: "Operations" },
  deferred_target_planning_year_id: null,
  deferred_target_planning_year: null,
  expense_count: 0,
  expenses: [],
  lock_version: 1,
  revision_activity: [],
};

describe("detail attachment tabs", () => {
  it("mounts Contract attachments lazily between details and history", async () => {
    vi.mocked(getContract).mockResolvedValue(contract);
    render(<MemoryRouter initialEntries={["/contracts/12"]}><Routes><Route path="/contracts/:contractId" element={<ContractDetail />} /></Routes></MemoryRouter>);

    await screen.findByText("Contratto demo");
    expect(screen.getAllByRole("tab").map((tab) => tab.textContent)).toEqual(["Dettagli", "Allegati", "Storico"]);
    expect(screen.queryByTestId("attachment-panel")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("tab", { name: "Allegati" }));
    expect(screen.getByTestId("attachment-panel")).toHaveTextContent("contract:12");
  });

  it("mounts Project attachments lazily between details and history", async () => {
    vi.mocked(getProject).mockResolvedValue(project);
    render(<MemoryRouter initialEntries={["/projects/23"]}><Routes><Route path="/projects/:projectId" element={<ProjectDetail />} /></Routes></MemoryRouter>);

    await screen.findByText("Progetto demo");
    expect(screen.getAllByRole("tab").map((tab) => tab.textContent)).toEqual(["Dettagli", "Allegati", "Storico"]);
    expect(screen.queryByTestId("attachment-panel")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("tab", { name: "Allegati" }));
    expect(screen.getByTestId("attachment-panel")).toHaveTextContent("project:23");
  });
});
