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

export default function ContractTermsEditor({ terms, onChange, disabled = false, existingTerms = [], validationErrors = {}, onFieldChange = () => undefined }: { terms: ContractTermInput[]; onChange: (terms: ContractTermInput[]) => void; disabled?: boolean; existingTerms?: ContractTerm[]; validationErrors?: Record<string, string>; onFieldChange?: (fields: string[]) => void }) {
  const [termToRemove, setTermToRemove] = useState<number | null>(null);
  const update = (index: number, patch: Partial<ContractTermInput>) => {
    onFieldChange(Object.keys(patch).map((field) => `terms.${index}.${field}`));
    onChange(terms.map((term, itemIndex) => itemIndex === index ? { ...term, ...patch } : term));
  };
  const updateEconomicFields = (index: number, patch: Partial<Pick<ContractTermInput, "quantity" | "unit_price" | "entered_amount">>) => update(index, applyCalculatedAmount(terms[index], patch));
  const remove = () => { if (termToRemove === null || terms.length <= 1 || terms[termToRemove]?.id !== undefined) return; onFieldChange(["terms"]); onChange(terms.filter((_, index) => index !== termToRemove)); setTermToRemove(null); };

  return <section id="contract-terms" tabIndex={-1} className="space-y-4 focus:outline-hidden focus:ring-3 focus:ring-error-500/10">
    <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Termini Contrattuali</h2><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Definisci validità, ciclo e importi di ciascun termine.</p></div><Button type="button" size="sm" variant="outline" onClick={() => { onFieldChange(["terms"]); onChange([...terms, newContractTerm()]); }} disabled={disabled}>Aggiungi Termine</Button></div>
    {validationErrors.terms ? <p className="text-xs text-error-500">{validationErrors.terms}</p> : null}
    {terms.map((term, index) => {
      const existing = term.id === undefined ? undefined : existingTerms.find((item) => item.id === term.id);
      const calculatedAmount = calculateEnteredAmount(term.quantity, term.unit_price);
      const errorFor = (field: string) => validationErrors[`terms.${index}.${field}`];
      const structuralError = validationErrors[`terms.${index}`] ?? errorFor("id") ?? errorFor("local_key") ?? errorFor("lock_version");
      const hasTermError = structuralError !== undefined || Object.keys(validationErrors).some((field) => field.startsWith(`terms.${index}.`));
      return <fieldset id={`contract-term-${index}`} tabIndex={-1} key={term.local_key} className={`rounded-2xl border bg-white p-4 focus:outline-hidden focus:ring-3 focus:ring-error-500/10 dark:bg-white/[0.02] sm:p-5 ${hasTermError ? "border-error-500 dark:border-error-500" : "border-gray-200 dark:border-gray-800"}`}>
        <legend className="sr-only">Termine {index + 1}</legend>
        <div className="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800"><h3 className="font-semibold text-gray-800 dark:text-white/90">Termine {index + 1}</h3>{terms.length > 1 && term.id === undefined ? <IconButton icon={TrashBinIcon} label={`Rimuovi il termine ${index + 1}`} onClick={() => setTermToRemove(index)} disabled={disabled} destructive /> : null}</div>
        {structuralError ? <p className="mb-4 text-xs text-error-500">{structuralError}</p> : null}
        <div className="space-y-6">
          <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Validità e Ciclo</h4><div className="grid grid-cols-1 gap-4 md:grid-cols-3"><DatePicker id={`term-${index}-start`} label="Data iniziale" placeholder="Seleziona la data" defaultDate={term.effective_start || undefined} onChange={(_, value) => update(index, { effective_start: value })} disabled={disabled} error={Boolean(errorFor("effective_start"))} hint={errorFor("effective_start")} /><DatePicker id={`term-${index}-end`} label="Data finale" placeholder="Seleziona la data" defaultDate={term.effective_end || undefined} onChange={(_, value) => update(index, { effective_end: value })} disabled={disabled} error={Boolean(errorFor("effective_end"))} hint={errorFor("effective_end")} /><div><Label htmlFor={`term-${index}-cycle`}>Ciclo di fatturazione</Label><Select id={`term-${index}-cycle`} options={[{ value: "monthly", label: "Mensile" }, { value: "annual", label: "Annuale" }]} value={term.billing_cycle} onChange={(billing_cycle) => update(index, { billing_cycle: billing_cycle as ContractTermInput["billing_cycle"] })} disabled={disabled} error={Boolean(errorFor("billing_cycle"))} hint={errorFor("billing_cycle")} /></div></div></section>
          <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Quantità e Importi</h4><div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"><div><Label htmlFor={`term-${index}-quantity`}>Quantità</Label><DecimalInput id={`term-${index}-quantity`} value={term.quantity ?? ""} onChange={(quantity) => updateEconomicFields(index, { quantity: quantity || null })} trimTrailingZeros disabled={disabled} error={Boolean(errorFor("quantity"))} hint={errorFor("quantity")} /></div><div><Label htmlFor={`term-${index}-unit-price`}>Prezzo unitario</Label><DecimalInput id={`term-${index}-unit-price`} value={term.unit_price ?? ""} onChange={(unit_price) => updateEconomicFields(index, { unit_price: unit_price || null })} trimTrailingZeros disabled={disabled} error={Boolean(errorFor("unit_price"))} hint={errorFor("unit_price")} /></div><div><Label htmlFor={`term-${index}-amount`}>Importo</Label><DecimalInput id={`term-${index}-amount`} value={term.entered_amount} onChange={(entered_amount) => update(index, { entered_amount })} fixedScale={2} readOnly={calculatedAmount !== null} error={Boolean(errorFor("entered_amount"))} hint={errorFor("entered_amount") ?? (calculatedAmount !== null ? "Calcolato da quantità × prezzo unitario" : undefined)} disabled={disabled} /></div><div><Label htmlFor={`term-${index}-vat`}>Aliquota IVA</Label><DecimalInput id={`term-${index}-vat`} value={term.vat_rate ?? ""} onChange={(vat_rate) => update(index, { vat_rate: vat_rate || null })} fixedScale={2} disabled={disabled} error={Boolean(errorFor("vat_rate"))} hint={errorFor("vat_rate")} /></div></div></section>
          <section><h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Opzioni</h4><div className="flex flex-wrap gap-6"><Checkbox id={`term-${index}-vat-included`} label="Importo IVA inclusa" checked={term.amount_includes_vat} onChange={(amount_includes_vat) => update(index, { amount_includes_vat })} disabled={disabled} error={Boolean(errorFor("amount_includes_vat"))} hint={errorFor("amount_includes_vat")} /><Checkbox id={`term-${index}-auto-renew`} label="Rinnovo automatico" checked={term.auto_renew} onChange={(auto_renew) => update(index, { auto_renew })} disabled={disabled} error={Boolean(errorFor("auto_renew"))} hint={errorFor("auto_renew")} /></div></section>
          {existing ? <div className="grid grid-cols-1 gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-3 dark:bg-white/[0.03]"><div><p className="text-xs text-gray-500 dark:text-gray-400">Netto</p><p className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(existing.net, existing.currency ?? "EUR")}</p></div><div><p className="text-xs text-gray-500 dark:text-gray-400">IVA ({formatPercentage(formatEditableDecimal(existing.vat_rate, { fixedScale: 2 }))})</p><p className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(existing.vat, existing.currency ?? "EUR")}</p></div><div><p className="text-xs text-gray-500 dark:text-gray-400">Lordo</p><p className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(existing.gross, existing.currency ?? "EUR")}</p></div></div> : null}
        </div>
      </fieldset>;
    })}
    <Modal isOpen={termToRemove !== null} onClose={() => setTermToRemove(null)} className="max-w-lg p-6"><h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Rimuovere il termine?</h2><p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Il termine non ancora salvato verrà escluso dal contratto.</p><div className="mt-6 flex justify-end gap-3"><Button variant="outline" onClick={() => setTermToRemove(null)}>Annulla</Button><Button onClick={remove}>Rimuovi Termine</Button></div></Modal>
  </section>;
}
