import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "../../api/client";
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
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listExpenseCostCenters).mockResolvedValue([{ id: 10, name: "IT" }]);
    vi.mocked(listExpensePlanningYears).mockResolvedValue([{ id: 20, label: 2027, active: true }]);
  });

  it("clears a deferred target before submitting a different stage", async () => {
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

  it("highlights and focuses a field rejected by the API", async () => {
    const error = new ApiError({
      message: "I dati inseriti non sono validi.",
      status: 422,
      fields: { cost_center_id: ["The selected cost center is invalid."] },
    });
    render(<ProjectForm submitting={false} error={error} onSubmit={vi.fn()} onCancel={vi.fn()} />);

    const costCenter = await screen.findByRole("combobox", { name: "Centro di costo" });
    expect(costCenter).toHaveAttribute("aria-invalid", "true");
    expect(costCenter).toHaveAccessibleDescription("Seleziona un Centro di costo disponibile.");
    await waitFor(() => expect(document.activeElement).toBe(costCenter));
  });

  it("focuses the first locally invalid field and keeps suggestions on the others", async () => {
    render(<ProjectForm submitting={false} error={null} onSubmit={vi.fn()} onCancel={vi.fn()} />);
    await screen.findByRole("option", { name: "IT" });

    fireEvent.click(screen.getByRole("button", { name: "Crea progetto" }));

    const title = screen.getByRole("textbox", { name: "Titolo" });
    expect(title).toHaveAttribute("aria-invalid", "true");
    expect(title).toHaveAccessibleDescription("Inserisci un titolo.");
    expect(screen.getByRole("combobox", { name: "Centro di costo" })).toHaveAttribute("aria-invalid", "true");
    await waitFor(() => expect(document.activeElement).toBe(title));
  });
});
