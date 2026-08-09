import { fireEvent, render, screen, within } from "@testing-library/react";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import DatePicker from "./date-picker";

function DateRange() {
  const [start, setStart] = useState("2026-08-01");
  const [end, setEnd] = useState("");

  return (
    <>
      <DatePicker id="start" label="Data iniziale" defaultDate={start || undefined} onChange={(_, value) => setStart(value)} />
      <DatePicker id="end" label="Data finale" defaultDate={end || undefined} onChange={(_, value) => setEnd(value)} />
    </>
  );
}

describe("DatePicker", () => {
  it("keeps one localized field visible per date after a selection", () => {
    render(<DateRange />);

    const startSource = document.querySelector<HTMLInputElement>("#start");
    const endSource = document.querySelector<HTMLInputElement>("#end");
    expect(startSource).toHaveAttribute("type", "hidden");
    expect(endSource).toHaveAttribute("type", "hidden");
    expect(screen.getAllByRole("textbox")).toHaveLength(2);

    fireEvent.click(screen.getByRole("button", { name: "Apri calendario: Data iniziale" }));
    const startWrapper = startSource?.closest(".flatpickr-wrapper");
    const day = within(startWrapper as HTMLElement).getByLabelText(/Agosto 15, 2026/i);
    fireEvent.click(day);

    expect(startSource).toHaveValue("2026-08-15");
    expect(screen.getByRole("textbox", { name: "Data iniziale" })).toHaveValue("15/08/2026");
    expect(screen.getByRole("textbox", { name: "Data finale" })).toHaveValue("");
    expect(screen.getAllByRole("textbox")).toHaveLength(2);

    fireEvent.click(screen.getByRole("button", { name: "Apri calendario: Data finale" }));
    expect(endSource?.closest(".flatpickr-wrapper")?.querySelector(".flatpickr-calendar")).toHaveClass("open");
  });
});
