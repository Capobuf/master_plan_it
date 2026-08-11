import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import ExpensePagination from "./ExpensePagination";

describe("ExpensePagination", () => {
  it("shows 25/50/100 page sizes and delegates a change to the register", () => {
    const onPerPageChange = vi.fn();

    render(
      <ExpensePagination
        meta={{ current_page: 2, last_page: 6, per_page: 25, total: 126 }}
        onPageChange={vi.fn()}
        onPerPageChange={onPerPageChange}
      />,
    );

    const selector = screen.getByRole("combobox", { name: "Righe per pagina" });
    expect(selector).toHaveValue("25");
    expect(screen.getAllByRole("option").map((option) => option.textContent)).toEqual(["25", "50", "100"]);

    fireEvent.change(selector, { target: { value: "50" } });
    expect(onPerPageChange).toHaveBeenCalledWith(50);
  });
});
