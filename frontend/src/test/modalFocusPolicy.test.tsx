import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import Checkbox from "../components/form/input/Checkbox";
import DecimalInput from "../components/form/input/DecimalInput";
import InputField from "../components/form/input/InputField";
import Select from "../components/form/Select";
import TextArea from "../components/form/input/TextArea";
import { Modal } from "../components/ui/modal";

const uiSources = import.meta.glob("../**/*.tsx", {
  eager: true,
  query: "?raw",
  import: "default",
}) as Record<string, string>;

function ApplicationControlsModal() {
  const [form, setForm] = useState({
    name: "",
    notes: "",
    amount: "",
    category: "",
    enabled: false,
  });

  return (
    <Modal isOpen onClose={() => undefined}>
      <InputField
        ariaLabel="Nome"
        value={form.name}
        onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
      />
      <label htmlFor="ui-notes">Note</label>
      <TextArea
        id="ui-notes"
        value={form.notes}
        onChange={(notes) => setForm((current) => ({ ...current, notes }))}
      />
      <DecimalInput
        id="ui-amount"
        ariaLabel="Importo"
        value={form.amount}
        onChange={(amount) => setForm((current) => ({ ...current, amount }))}
      />
      <Select
        ariaLabel="Categoria"
        options={[{ value: "standard", label: "Standard" }]}
        value={form.category}
        onChange={(category) => setForm((current) => ({ ...current, category }))}
      />
      <Checkbox
        label="Attivo"
        checked={form.enabled}
        onChange={(enabled) => setForm((current) => ({ ...current, enabled }))}
      />
    </Modal>
  );
}

async function expectFocusAfterUpdate(element: HTMLElement, update: () => void) {
  element.focus();
  update();

  await act(async () => {
    await new Promise((resolve) => window.setTimeout(resolve, 20));
  });

  expect(element).toHaveFocus();
}

describe("application-wide modal focus policy", () => {
  it("keeps focus on every shared form control after a state update", async () => {
    render(<ApplicationControlsModal />);

    const name = screen.getByRole("textbox", { name: "Nome" });
    await waitFor(() => expect(name).toHaveFocus());

    await expectFocusAfterUpdate(name, () => {
      fireEvent.change(name, { target: { value: "A" } });
    });

    const notes = screen.getByRole("textbox", { name: "Note" });
    await expectFocusAfterUpdate(notes, () => {
      fireEvent.change(notes, { target: { value: "Nota" } });
    });

    const amount = screen.getByRole("textbox", { name: "Importo" });
    await expectFocusAfterUpdate(amount, () => {
      fireEvent.change(amount, { target: { value: "10,50" } });
    });

    const category = screen.getByRole("combobox", { name: "Categoria" });
    await expectFocusAfterUpdate(category, () => {
      fireEvent.change(category, { target: { value: "standard" } });
    });

    const enabled = screen.getByRole("checkbox", { name: "Attivo" });
    await expectFocusAfterUpdate(enabled, () => {
      fireEvent.click(enabled);
    });
  });

  it("routes every accessible application dialog through the shared Modal", () => {
    const dialogImplementations = Object.entries(uiSources)
      .filter(([path]) => !path.endsWith(".test.tsx"))
      .filter(([, source]) => /role=["']dialog["']|aria-modal/.test(source))
      .map(([path]) => path);

    expect(dialogImplementations).toEqual(["../components/ui/modal/index.tsx"]);
  });
});
