import { useState } from "react";

import type { ContractTerm, ContractTermInput } from "../../api/contracts";
import Checkbox from "../form/input/Checkbox";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Select from "../form/Select";

interface ContractTermsEditorProps {
  terms: ContractTermInput[];
  onChange: (terms: ContractTermInput[]) => void;
  disabled?: boolean;
  existingTerms?: ContractTerm[];
}

const emptyTerm = (): ContractTermInput => ({
  local_key: "",
  effective_start: "",
  effective_end: "",
  billing_cycle: "monthly",
  quantity: null,
  unit_price: null,
  entered_amount: "",
  amount_includes_vat: false,
  vat_rate: "",
  auto_renew: false,
});

export default function ContractTermsEditor({
  terms,
  onChange,
  disabled = false,
  existingTerms = [],
}: ContractTermsEditorProps) {
  const [termToRemove, setTermToRemove] = useState<number | null>(null);

  const updateTerm = (index: number, patch: Partial<ContractTermInput>) => {
    onChange(terms.map((term, termIndex) => (termIndex === index ? { ...term, ...patch } : term)));
  };

  const removeTerm = (index: number) => {
    if (terms.length <= 1) return;
    onChange(terms.filter((_, termIndex) => termIndex !== index));
    setTermToRemove(null);
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 className="text-base font-medium text-gray-800 dark:text-white/90">Contract terms</h3>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Amounts remain decimal strings and are calculated by Laravel.</p>
        </div>
        <button type="button" className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]" onClick={() => onChange([...terms, emptyTerm()])} disabled={disabled}>Add term</button>
      </div>

      {terms.map((term, index) => {
        const existing = existingTerms[index];
        return (
          <fieldset key={`${term.id ?? "new"}-${index}`} className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <legend className="px-2 text-sm font-medium text-gray-700 dark:text-gray-300">Term {index + 1}</legend>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <div><Label htmlFor={`term-${index}-key`}>Local key</Label><InputField id={`term-${index}-key`} value={term.local_key} onChange={(event) => updateTerm(index, { local_key: event.target.value })} disabled={disabled} /></div>
              <div><Label htmlFor={`term-${index}-start`}>Effective start</Label><InputField id={`term-${index}-start`} type="date" value={term.effective_start} onChange={(event) => updateTerm(index, { effective_start: event.target.value })} disabled={disabled} /></div>
              <div><Label htmlFor={`term-${index}-end`}>Effective end</Label><InputField id={`term-${index}-end`} type="date" value={term.effective_end} onChange={(event) => updateTerm(index, { effective_end: event.target.value })} disabled={disabled} /></div>
              <div><Label>Billing cycle</Label><Select options={[{ value: "monthly", label: "Monthly" }, { value: "annual", label: "Annual" }]} defaultValue={term.billing_cycle} onChange={(value) => updateTerm(index, { billing_cycle: value as ContractTermInput["billing_cycle"] })} /></div>
              <div><Label htmlFor={`term-${index}-quantity`}>Quantity</Label><InputField id={`term-${index}-quantity`} value={term.quantity ?? ""} onChange={(event) => updateTerm(index, { quantity: event.target.value || null })} disabled={disabled} /></div>
              <div><Label htmlFor={`term-${index}-unit-price`}>Unit price</Label><InputField id={`term-${index}-unit-price`} value={term.unit_price ?? ""} onChange={(event) => updateTerm(index, { unit_price: event.target.value || null })} disabled={disabled} /></div>
              <div><Label htmlFor={`term-${index}-amount`}>Entered amount</Label><InputField id={`term-${index}-amount`} value={term.entered_amount} onChange={(event) => updateTerm(index, { entered_amount: event.target.value })} disabled={disabled} /></div>
              <div><Label htmlFor={`term-${index}-vat`}>VAT rate</Label><InputField id={`term-${index}-vat`} value={term.vat_rate ?? ""} onChange={(event) => updateTerm(index, { vat_rate: event.target.value || null })} disabled={disabled} /></div>
            </div>
            <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
              <div className="flex flex-wrap gap-5">
                <Checkbox label="Amount includes VAT" checked={term.amount_includes_vat} onChange={(checked) => updateTerm(index, { amount_includes_vat: checked })} disabled={disabled} />
                <Checkbox label="Auto renew" checked={term.auto_renew} onChange={(checked) => updateTerm(index, { auto_renew: checked })} disabled={disabled} />
              </div>
              {terms.length > 1 ? <button type="button" className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]" onClick={() => setTermToRemove(index)} disabled={disabled}>Remove term</button> : null}
            </div>
            {existing ? <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">Server totals: net {existing.net} · VAT {existing.vat} · gross {existing.gross} {existing.currency ?? ""}</p> : null}
            {termToRemove === index ? (
              <div className="mt-3 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                <p>Remove this term from the contract submission?</p>
                <div className="mt-2 flex gap-2"><button type="button" className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600" onClick={() => removeTerm(index)}>Confirm</button><button type="button" className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300" onClick={() => setTermToRemove(null)}>Cancel</button></div>
              </div>
            ) : null}
          </fieldset>
        );
      })}
    </div>
  );
}
