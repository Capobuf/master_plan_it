import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import { expect, it } from "vitest";
import PlafondRegister from "./PlafondRegister";

it("links a register row to its dedicated detail and retains the Plafond cost center", () => {
  render(<MemoryRouter><PlafondRegister plafonds={[{ id: 41, planning_year_id: 25, economic_year_label: 2026, title: "Plafond Infrastruttura", notes: null, cost_center: { id: 9, name: "Infrastruttura" }, lock_version: 3, currency: "EUR", basis: "net", measures: { allocation: { net: "1", vat: "2", gross: "3", official: "3500.00" }, coverage_planned: { net: "1", vat: "2", gross: "3", official: "4200.00" }, consumed: { net: "1", vat: "2", gross: "3", official: "2500.00" }, available: { net: "1", vat: "2", gross: "3", official: "1000.00" } } }]} /></MemoryRouter>);
  expect(screen.getByRole("link", { name: "Plafond Infrastruttura" })).toHaveAttribute("href", "/plafonds/41");
  expect(screen.getByText(/Centro di Costo: Infrastruttura/)).toBeInTheDocument();
});
