import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { listExpenseCostCenters, listExpensePlanningYears } from "../../api/expenses";
import ProjectForm from "./ProjectForm";

vi.mock("../../api/expenses", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/expenses")>();
  return {
    ...actual,
    listExpenseCostCenters: vi.fn(),
    listExpensePlanningYears: vi.fn(),
  };
});

describe("ProjectForm", () => {
  it("clears a deferred target before submitting a different stage", async () => {
    vi.mocked(listExpenseCostCenters).mockResolvedValue([{ id: 10, name: "IT" }]);
    vi.mocked(listExpensePlanningYears).mockResolvedValue([{ id: 20, label: 2027, active: true }]);
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    render(
      <ProjectForm
        submitting={false}
        error={null}
        onSubmit={onSubmit}
        onCancel={vi.fn()}
      />,
    );

    await screen.findByRole("option", { name: "IT" });
    fireEvent.change(screen.getByLabelText("Titolo"), { target: { value: "Migrazione ERP" } });
    fireEvent.change(screen.getByLabelText("Centro di costo"), { target: { value: "10" } });
    fireEvent.change(screen.getByLabelText("Stage"), { target: { value: "deferred" } });
    expect(screen.getByLabelText("Anno di destinazione")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Anno di destinazione"), { target: { value: "20" } });
    fireEvent.change(screen.getByLabelText("Stage"), { target: { value: "approved" } });

    expect(screen.queryByLabelText("Anno di destinazione")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Crea progetto" }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith({
        title: "Migrazione ERP",
        cost_center_id: 10,
        stage: "approved",
        deferred_target_planning_year_id: null,
      });
    });
  });
});
