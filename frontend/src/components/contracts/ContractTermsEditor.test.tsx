import { fireEvent, render, screen } from "@testing-library/react";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import type { ContractTermInput } from "../../api/contracts";
import ContractTermsEditor from "./ContractTermsEditor";

const term: ContractTermInput = {
  local_key: "test-term",
  effective_start: "",
  effective_end: "",
  billing_cycle: "monthly",
  quantity: null,
  unit_price: null,
  entered_amount: "0.00",
  amount_includes_vat: false,
  vat_rate: null,
  auto_renew: false,
};

function TermsHarness() {
  const [terms, setTerms] = useState([term]);
  return <ContractTermsEditor terms={terms} onChange={setTerms} />;
}

function EmptyTermsHarness() {
  const [terms, setTerms] = useState<ContractTermInput[]>([
    { ...term, vat_rate: null },
  ]);
  return (
    <>
      <ContractTermsEditor terms={terms} onChange={setTerms} />
      <output data-testid="vat-values">
        {JSON.stringify(terms.map((item) => item.vat_rate))}
      </output>
    </>
  );
}

describe("ContractTermsEditor", () => {
  it("populates the amount from quantity and unit price", () => {
    render(<TermsHarness />);

    fireEvent.change(screen.getByLabelText("Quantità"), { target: { value: "2" } });
    fireEvent.change(screen.getByLabelText("Prezzo unitario"), { target: { value: "12,50" } });

    expect(screen.getByLabelText("Importo")).toHaveValue("25,00");
    expect(screen.getByLabelText("Importo")).toHaveAttribute("readonly");
    expect(screen.getByText("Calcolato da quantità × prezzo unitario")).toBeInTheDocument();
  });

  it("preserves omission for new terms and explicit VAT overrides", () => {
    render(<EmptyTermsHarness />);

    fireEvent.click(screen.getByRole("button", { name: "Aggiungi Termine" }));
    expect(screen.getByTestId("vat-values")).toHaveTextContent("[null,null]");

    fireEvent.change(screen.getAllByRole("textbox", { name: "Aliquota IVA" })[1], {
      target: { value: "10,00" },
    });
    expect(screen.getByTestId("vat-values")).toHaveTextContent('[null,"10,00"]');
  });
});
