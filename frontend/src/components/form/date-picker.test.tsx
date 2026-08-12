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

  it("associates an error only with the visible localized field", () => {
    render(<DatePicker id="renewal" label="Data di rinnovo" error hint="Inserisci una data valida." />);

    const source = document.querySelector<HTMLInputElement>("#renewal");
    const visible = screen.getByRole("textbox", { name: "Data di rinnovo" });
    expect(source).not.toHaveAttribute("aria-invalid");
    expect(visible).toHaveAttribute("aria-invalid", "true");
    expect(visible).toHaveAccessibleDescription("Inserisci una data valida.");
  });

  it("disables dates outside the configured range", () => {
    render(<DatePicker id="history" label="Vista temporale" defaultDate="2026-08-10" minDate="2026-08-09" maxDate="2026-08-12" />);

    fireEvent.click(screen.getByRole("button", { name: "Apri calendario: Vista temporale" }));
    const wrapper = document.querySelector<HTMLInputElement>("#history")?.closest(".flatpickr-wrapper");
    expect(within(wrapper as HTMLElement).getByLabelText(/Agosto 8, 2026/i)).toHaveClass("flatpickr-disabled");
    expect(within(wrapper as HTMLElement).getByLabelText(/Agosto 9, 2026/i)).not.toHaveClass("flatpickr-disabled");
    expect(within(wrapper as HTMLElement).getByLabelText(/Agosto 13, 2026/i)).toHaveClass("flatpickr-disabled");
  });
});
