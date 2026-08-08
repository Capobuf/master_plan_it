import { useCallback, useState } from "react";

import type { ContractTerm, ContractTermInput } from "../../api/contracts";
import Checkbox from "../form/input/Checkbox";
import DatePicker from "../form/date-picker";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Select from "../form/Select";
import Button from "../ui/button/Button";

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
    if (terms.length <= 1 || terms[index]?.id !== undefined) return;
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
        <Button size="sm" variant="outline" onClick={() => onChange([...terms, emptyTerm()])} disabled={disabled}>Add term</Button>
      </div>

      {terms.map((term, index) => {
        const existing = term.id === undefined ? undefined : existingTerms.find((candidate) => candidate.id === term.id);
        return (
          <fieldset key={`${term.id ?? "new"}-${index}`} className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <legend className="px-2 text-sm font-medium text-gray-700 dark:text-gray-300">Term {index + 1}</legend>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <div><Label htmlFor={`term-${index}-key`}>Local key</Label><InputField id={`term-${index}-key`} value={term.local_key} onChange={(event) => updateTerm(index, { local_key: event.target.value })} disabled={disabled} /></div>
              <TermDateField id={`term-${index}-start`} label="Effective start" value={term.effective_start} onChange={(value) => updateTerm(index, { effective_start: value })} disabled={disabled} />
              <TermDateField id={`term-${index}-end`} label="Effective end" value={term.effective_end} onChange={(value) => updateTerm(index, { effective_end: value })} disabled={disabled} />
              <div><Label>Billing cycle</Label><Select options={[{ value: "monthly", label: "Monthly" }, { value: "annual", label: "Annual" }]} defaultValue={term.billing_cycle} onChange={(value) => updateTerm(index, { billing_cycle: value as ContractTermInput["billing_cycle"] })} /></div>
              <div><Label htmlFor={`term-${index}-quantity`}>Quantity</Label><InputField id={`term-${index}-quantity`} value={term.quantity ?? ""} onChange={(event) => updateTerm(index, { quantity: event.target.value || null })} disabled={disabled} /></div>
              <div><Label htmlFor={`term-${index}-unit-price`}>Unit price</Label><InputField id={`term-${index}-unit-price`} value={term.unit_price ?? ""} onChange={(event) => updateTerm(index, { unit_price: event.target.value || null })} disabled={disabled} /></div>
              <div><Label htmlFor={`term-${index}-amount`}>Entered amount</Label><InputField id={`term-${index}-amount`} value={term.entered_amount} onChange={(event) => updateTerm(index, { entered_amount: event.target.value })} disabled={disabled} /></div>
              <div><Label htmlFor={`term-${index}-vat`}>VAT rate</Label><InputField id={`term-${index}-vat`} value={term.vat_rate ?? ""} onChange={(event) => updateTerm(index, { vat_rate: event.target.value || null })} disabled={disabled} /></div>
            </div>
            <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
              <div className="flex flex-wrap gap-5"><Checkbox label="Amount includes VAT" checked={term.amount_includes_vat} onChange={(checked) => updateTerm(index, { amount_includes_vat: checked })} disabled={disabled} /><Checkbox label="Auto renew" checked={term.auto_renew} onChange={(checked) => updateTerm(index, { auto_renew: checked })} disabled={disabled} /></div>
              {terms.length > 1 && term.id === undefined ? <Button size="sm" variant="outline" onClick={() => setTermToRemove(index)} disabled={disabled}>Remove new term</Button> : null}
            </div>
            {existing ? <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">Server totals: net {existing.net} · VAT {existing.vat} · gross {existing.gross} {existing.currency ?? ""}</p> : null}
            {termToRemove === index ? <div className="mt-3 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300"><p>Remove this unsaved term from the submission?</p><div className="mt-2 flex gap-2"><Button size="sm" onClick={() => removeTerm(index)}>Confirm</Button><Button size="sm" variant="outline" onClick={() => setTermToRemove(null)}>Cancel</Button></div></div> : null}
          </fieldset>
        );
      })}
    </div>
  );
}

function TermDateField({ id, label, value, onChange, disabled }: { id: string; label: string; value: string; onChange: (value: string) => void; disabled: boolean }) {
  const handleChange = useCallback((_: unknown, dateString: string) => onChange(dateString), [onChange]);
  return <div className={disabled ? "opacity-60" : ""}><DatePicker id={id} label={label} placeholder="Select date" defaultDate={value || undefined} onChange={handleChange} /></div>;
}
