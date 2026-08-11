import { useState } from "react";
import type { ContractTerm, ContractTermInput } from "../../api/contracts";
import { TrashBinIcon } from "../../icons";
import { applyCalculatedAmount, calculateEnteredAmount, formatEditableDecimal, formatMoney, formatPercentage } from "../../presentation/formatters";
import IconButton from "../common/IconButton";
import DatePicker from "../form/date-picker";
import Label from "../form/Label";
import Select from "../form/Select";
import Checkbox from "../form/input/Checkbox";
import DecimalInput from "../form/input/DecimalInput";
import Button from "../ui/button/Button";
import { Modal } from "../ui/modal";
import { newContractTerm } from "./contractTermFactory";

export default function ContractTermsEditor({ terms, onChange, disabled = false, existingTerms = [] }: { terms: ContractTermInput[]; onChange: (terms: ContractTermInput[]) => void; disabled?: boolean; existingTerms?: ContractTerm[] }) {
  const [termToRemove, setTermToRemove] = useState<number | null>(null);
  const update = (index: number, patch: Partial<ContractTermInput>) => onChange(terms.map((term, itemIndex) => itemIndex === index ? { ...term, ...patch } : term));
  const updateEconomicFields = (index: number, patch: Partial<Pick<ContractTermInput, "quantity" | "unit_price" | "entered_amount">>) => update(index, applyCalculatedAmount(terms[index], patch));
  const remove = () => { if (termToRemove === null || terms.length <= 1 || terms[termToRemove]?.id !== undefined) return; onChange(terms.filter((_, index) => index !== termToRemove)); setTermToRemove(null); };

  return <section className="space-y-4">
    <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Termini Contrattuali</h2><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Definisci validità, ciclo e importi di ciascun termine.</p></div><Button type="button" size="sm" variant="outline" onClick={() => onChange([...terms, newContractTerm()])} disabled={disabled}>Aggiungi Termine</Button></div>
    {terms.map((term, index) => {
      const existing = term.id === undefined ? undefined : existingTerms.find((item) => item.id === term.id);
      const calculatedAmount = calculateEnteredAmount(term.quantity, term.unit_price);
      return <fieldset key={term.local_key} className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.02] sm:p-5">
        <legend className="sr-only">Termine {index + 1}</legend>
        <div className="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800"><h3 className="font-semibold text-gray-800 dark:text-white/90">Termine {index + 1}</h3>{terms.length > 1 && term.id === undefined ? <IconButton icon={TrashBinIcon} label={`Rimuovi il termine ${index + 1}`} onClick={() => setTermToRemove(index)} disabled={disabled} destructive /> : null}</div>
        <div className="space-y-6">
          <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Validità e Ciclo</h4><div className="grid grid-cols-1 gap-4 md:grid-cols-3"><DatePicker id={`term-${index}-start`} label="Data iniziale" placeholder="Seleziona la data" defaultDate={term.effective_start || undefined} onChange={(_, value) => update(index, { effective_start: value })} disabled={disabled} /><DatePicker id={`term-${index}-end`} label="Data finale" placeholder="Seleziona la data" defaultDate={term.effective_end || undefined} onChange={(_, value) => update(index, { effective_end: value })} disabled={disabled} /><div><Label htmlFor={`term-${index}-cycle`}>Ciclo di fatturazione</Label><Select id={`term-${index}-cycle`} options={[{ value: "monthly", label: "Mensile" }, { value: "annual", label: "Annuale" }]} value={term.billing_cycle} onChange={(billing_cycle) => update(index, { billing_cycle: billing_cycle as ContractTermInput["billing_cycle"] })} disabled={disabled} /></div></div></section>
          <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Quantità e Importi</h4><div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"><div><Label htmlFor={`term-${index}-quantity`}>Quantità</Label><DecimalInput id={`term-${index}-quantity`} value={term.quantity ?? ""} onChange={(quantity) => updateEconomicFields(index, { quantity: quantity || null })} trimTrailingZeros disabled={disabled} /></div><div><Label htmlFor={`term-${index}-unit-price`}>Prezzo unitario</Label><DecimalInput id={`term-${index}-unit-price`} value={term.unit_price ?? ""} onChange={(unit_price) => updateEconomicFields(index, { unit_price: unit_price || null })} trimTrailingZeros disabled={disabled} /></div><div><Label htmlFor={`term-${index}-amount`}>Importo</Label><DecimalInput id={`term-${index}-amount`} value={term.entered_amount} onChange={(entered_amount) => update(index, { entered_amount })} fixedScale={2} readOnly={calculatedAmount !== null} hint={calculatedAmount !== null ? "Calcolato da quantità × prezzo unitario" : undefined} disabled={disabled} /></div><div><Label htmlFor={`term-${index}-vat`}>Aliquota IVA</Label><DecimalInput id={`term-${index}-vat`} value={term.vat_rate ?? ""} onChange={(vat_rate) => update(index, { vat_rate: vat_rate || null })} fixedScale={2} disabled={disabled} /></div></div></section>
          <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Opzioni</h4><div className="flex flex-wrap gap-6"><Checkbox label="Importo IVA inclusa" checked={term.amount_includes_vat} onChange={(amount_includes_vat) => update(index, { amount_includes_vat })} disabled={disabled} /><Checkbox label="Rinnovo automatico" checked={term.auto_renew} onChange={(auto_renew) => update(index, { auto_renew })} disabled={disabled} /></div></section>
          {existing ? <div className="grid grid-cols-1 gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-3 dark:bg-white/[0.03]"><div><p className="text-xs text-gray-500 dark:text-gray-400">Netto</p><p className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(existing.net, existing.currency ?? "EUR")}</p></div><div><p className="text-xs text-gray-500 dark:text-gray-400">IVA ({formatPercentage(formatEditableDecimal(existing.vat_rate, { fixedScale: 2 }))})</p><p className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(existing.vat, existing.currency ?? "EUR")}</p></div><div><p className="text-xs text-gray-500 dark:text-gray-400">Lordo</p><p className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(existing.gross, existing.currency ?? "EUR")}</p></div></div> : null}
        </div>
      </fieldset>;
    })}
    <Modal isOpen={termToRemove !== null} onClose={() => setTermToRemove(null)} className="max-w-lg p-6"><h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Rimuovere il termine?</h2><p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Il termine non ancora salvato verrà escluso dal contratto.</p><div className="mt-6 flex justify-end gap-3"><Button variant="outline" onClick={() => setTermToRemove(null)}>Annulla</Button><Button onClick={remove}>Rimuovi Termine</Button></div></Modal>
  </section>;
}
