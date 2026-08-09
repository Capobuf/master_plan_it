import { fireEvent, render, screen } from "@testing-library/react";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import DecimalInput from "./DecimalInput";

function ControlledDecimalInput({ initialValue = "0.00" }: { initialValue?: string }) {
  const [value, setValue] = useState(initialValue);
  return <DecimalInput id="decimal" value={value} onChange={setValue} fixedScale={2} ariaLabel="Importo" />;
}

describe("DecimalInput", () => {
  it("does not append the fixed decimal scale while the user is typing", () => {
    render(<ControlledDecimalInput />);
    const input = screen.getByRole("textbox", { name: "Importo" });

    fireEvent.change(input, { target: { value: "1" } });
    expect(input).toHaveValue("1");

    fireEvent.change(input, { target: { value: "12,3" } });
    expect(input).toHaveValue("12,3");

    fireEvent.blur(input);
    expect(input).toHaveValue("12,30");
  });

  it("still formats values supplied externally", () => {
    const { rerender } = render(
      <DecimalInput id="decimal" value="10" onChange={() => undefined} fixedScale={2} ariaLabel="IVA" />,
    );

    expect(screen.getByRole("textbox", { name: "IVA" })).toHaveValue("10,00");
    rerender(<DecimalInput id="decimal" value="8.5" onChange={() => undefined} fixedScale={2} ariaLabel="IVA" />);
    expect(screen.getByRole("textbox", { name: "IVA" })).toHaveValue("8,50");
  });
});
