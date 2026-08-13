import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { describe, expect, it } from "vitest";
import Alert from "./Alert";

describe("Alert", () => {
  it("uses one assertive alert region for errors", () => {
    render(<MemoryRouter><Alert variant="error" title="Errore" message="Correggi la data." /></MemoryRouter>);
    const alert = screen.getByRole("alert");
    expect(alert).toHaveAttribute("aria-live", "assertive");
    expect(screen.queryAllByRole("alert")).toHaveLength(1);
  });

  it("uses a polite status region for non-error notices", () => {
    render(<MemoryRouter><Alert variant="warning" title="Avviso" message="Aggiorna la vista." /></MemoryRouter>);
    expect(screen.getByRole("status")).toHaveAttribute("aria-live", "polite");
  });
});
