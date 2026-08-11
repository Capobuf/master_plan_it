import { useCallback, useEffect, useState } from "react";
import type { Contract, ContractTermInput, ContractUpdate, ContractWrite } from "../../api/contracts";
import { listContractCostCenters, listContractVendors, type ContractLookupOption } from "../../api/contracts";
import { listProjectOptions, type ProjectLookupOption } from "../../api/projects";
import { formatEditableDecimal, normalizeDecimalInput } from "../../presentation/formatters";
import ComponentCard from "../common/ComponentCard";
import DatePicker from "../form/date-picker";
import Label from "../form/Label";
import Select from "../form/Select";
import Checkbox from "../form/input/Checkbox";
import InputField from "../form/input/InputField";
import TextArea from "../form/input/TextArea";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";
import ContractTermsEditor from "./ContractTermsEditor";
import { newContractTerm } from "./contractTermFactory";

function toTermInput(term: Contract["terms"][number]): ContractTermInput {
  return { id: term.id ?? undefined, local_key: term.local_key, effective_start: term.effective_start, effective_end: term.effective_end, billing_cycle: term.billing_cycle, quantity: term.quantity ? formatEditableDecimal(term.quantity, { trimTrailingZeros: true }) : null, unit_price: term.unit_price ? formatEditableDecimal(term.unit_price, { trimTrailingZeros: true }) : null, entered_amount: formatEditableDecimal(term.entered_amount, { fixedScale: 2 }), amount_includes_vat: term.amount_includes_vat, vat_rate: formatEditableDecimal(term.vat_rate, { fixedScale: 2 }), auto_renew: term.auto_renew, lock_version: term.lock_version };
}

export default function ContractForm({ contract = null, canSubmit, submitting = false, error = null, onSubmit }: { contract?: Contract | null; canSubmit: boolean; submitting?: boolean; error?: string | null; onSubmit: (input: ContractWrite | ContractUpdate) => Promise<void> }) {
  const [vendorId, setVendorId] = useState(String(contract?.vendor_id ?? ""));
  const [costCenterId, setCostCenterId] = useState(String(contract?.cost_center_id ?? ""));
  const [projectId, setProjectId] = useState(String(contract?.project_id ?? ""));
  const [title, setTitle] = useState(contract?.title ?? "");
  const [description, setDescription] = useState(contract?.description ?? "");
  const [active, setActive] = useState(contract?.active ?? true);
  const [renewalDate, setRenewalDate] = useState(contract?.renewal_date ?? "");
  const [renewalNoticeDays, setRenewalNoticeDays] = useState(String(contract?.renewal_notice_days ?? ""));
  const [renewalNotes, setRenewalNotes] = useState(contract?.renewal_notes ?? "");
  const [terms, setTerms] = useState<ContractTermInput[]>(() => contract?.terms.map(toTermInput) ?? [newContractTerm()]);
  const [validationMessage, setValidationMessage] = useState<string | null>(null);
  const [vendors, setVendors] = useState<ContractLookupOption[]>([]);
  const [costCenters, setCostCenters] = useState<ContractLookupOption[]>([]);
  const [projects, setProjects] = useState<ProjectLookupOption[]>([]);
  const [lookupError, setLookupError] = useState<string | null>(null);
  const handleRenewalDate = useCallback((_: unknown, value: string) => setRenewalDate(value), []);

  useEffect(() => {
    if (!canSubmit) return;
    let mounted = true;
    void Promise.all([listContractVendors(), listContractCostCenters(), listProjectOptions()]).then(([vendorResponse, costCenterResponse, projectResponse]) => { if (mounted) { setVendors(vendorResponse.data); setCostCenters(costCenterResponse.data); setProjects(projectResponse); } }).catch((requestError: unknown) => { if (mounted) setLookupError(requestError instanceof Error ? requestError.message : "Impossibile caricare i dati di supporto."); });
    return () => { mounted = false; };
  }, [canSubmit]);

  const submit = async () => {
    setValidationMessage(null);
    const parsedVendorId = Number.parseInt(vendorId, 10);
    const parsedCostCenterId = Number.parseInt(costCenterId, 10);
    if (!vendorId || !costCenterId || !Number.isInteger(parsedVendorId) || !Number.isInteger(parsedCostCenterId)) { setValidationMessage("Seleziona un fornitore e un centro di costo."); return; }
    if (!title.trim() || terms.length === 0) { setValidationMessage("Inserisci il titolo e almeno un termine contrattuale."); return; }
    try {
      const preparedTerms = terms.map((term) => ({ ...term, entered_amount: normalizeDecimalInput(term.entered_amount, 2), vat_rate: term.vat_rate ? normalizeDecimalInput(term.vat_rate, 2) : null, quantity: term.quantity ? normalizeDecimalInput(term.quantity, 2) : null, unit_price: term.unit_price ? normalizeDecimalInput(term.unit_price, 2) : null }));
      if (preparedTerms.some((term) => !term.effective_start || !term.effective_end)) { setValidationMessage("Indica la data iniziale e finale di ogni termine."); return; }
      const input: ContractWrite = { vendor_id: parsedVendorId, cost_center_id: parsedCostCenterId, ...(projectId ? { project_id: Number.parseInt(projectId, 10) } : {}), title: title.trim(), description: description.trim() || undefined, active, renewal_date: renewalDate || null, renewal_notice_days: renewalNoticeDays === "" ? null : Number.parseInt(renewalNoticeDays, 10), renewal_notes: renewalNotes.trim() || undefined, terms: preparedTerms };
      await onSubmit(contract ? { ...input, lock_version: contract.lock_version } : input);
    } catch (validationError) { setValidationMessage(validationError instanceof Error ? validationError.message : "Controlla gli importi inseriti."); }
  };
  const disabled = !canSubmit || submitting;

  return <form className="space-y-6" onSubmit={(event) => { event.preventDefault(); void submit(); }}>
    {error ? <Alert variant="error" title="Salvataggio non riuscito" message={error} /> : null}
    {lookupError ? <Alert variant="warning" title="Dati di supporto non disponibili" message={lookupError} /> : null}
    {validationMessage ? <Alert variant="warning" title="Controlla i campi" message={validationMessage} /> : null}
    <ComponentCard title="Dati del Contratto"><div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div><Label htmlFor="contract-vendor">Fornitore</Label><Select id="contract-vendor" options={vendors.map((vendor) => ({ value: String(vendor.id), label: `${vendor.name}${vendor.active === false ? " (inattivo)" : ""}` }))} placeholder={vendors.length === 0 ? "Caricamento fornitori…" : "Seleziona un fornitore"} value={vendorId} onChange={setVendorId} disabled={disabled} /></div>
      <div><Label htmlFor="contract-cost-center">Centro di costo</Label><Select id="contract-cost-center" options={costCenters.map((item) => ({ value: String(item.id), label: `${item.name}${item.active === false ? " (inattivo)" : ""}` }))} placeholder={costCenters.length === 0 ? "Caricamento centri di costo…" : "Seleziona un centro di costo"} value={costCenterId} onChange={setCostCenterId} disabled={disabled} /></div>
      <div><Label htmlFor="contract-project">Progetto (opzionale)</Label><Select id="contract-project" options={projects.map((item) => ({ value: String(item.id), label: item.title }))} placeholder="Nessun progetto" allowEmpty value={projectId} onChange={setProjectId} disabled={disabled} /></div>
      <div className="md:col-span-2"><Label htmlFor="contract-title">Titolo</Label><InputField id="contract-title" value={title} onChange={(event) => setTitle(event.target.value)} disabled={disabled} /></div>
      <div className="md:col-span-2"><Label>Descrizione</Label><TextArea value={description} onChange={setDescription} disabled={disabled} placeholder="Descrizione opzionale" /></div>
    </div></ComponentCard>
    <ComponentCard title="Rinnovo"><div className="grid grid-cols-1 gap-4 md:grid-cols-2"><DatePicker id="contract-renewal-date" label="Data di rinnovo" placeholder="Seleziona la data" defaultDate={renewalDate || undefined} onChange={handleRenewalDate} disabled={disabled} /><div><Label htmlFor="contract-renewal-notice">Giorni di preavviso</Label><InputField id="contract-renewal-notice" type="number" min="0" value={renewalNoticeDays} onChange={(event) => setRenewalNoticeDays(event.target.value)} disabled={disabled} /></div><div className="md:col-span-2"><Label htmlFor="contract-renewal-notes">Note sul rinnovo</Label><InputField id="contract-renewal-notes" value={renewalNotes} onChange={(event) => setRenewalNotes(event.target.value)} disabled={disabled} placeholder="Note opzionali" /></div><div className="md:col-span-2"><Checkbox label="Contratto attivo" checked={active} onChange={setActive} disabled={disabled} /></div></div></ComponentCard>
    <ContractTermsEditor terms={terms} onChange={setTerms} disabled={disabled} existingTerms={contract?.terms} />
    <div className="flex justify-end"><Button disabled={disabled}>{submitting ? "Salvataggio…" : contract ? "Salva Modifiche" : "Crea Contratto"}</Button></div>
  </form>;
}
