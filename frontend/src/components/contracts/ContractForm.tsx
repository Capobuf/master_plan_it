import { useEffect, useState } from "react";
import { ApiError } from "../../api/client";
import type { Contract, ContractTermInput, ContractUpdate, ContractWrite } from "../../api/contracts";
import { listContractCostCenters, listContractVendors, type ContractLookupOption } from "../../api/contracts";
import { listProjectOptions, type ProjectLookupOption } from "../../api/projects";
import { useInvalidFieldFocus, type InvalidFieldFocusRequest } from "../../hooks/useInvalidFieldFocus";
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

const contractFieldIds: Record<string, string> = {
  vendor_id: "contract-vendor",
  cost_center_id: "contract-cost-center",
  project_id: "contract-project",
  title: "contract-title",
  description: "contract-description",
  active: "contract-active",
  renewal_date: "contract-renewal-date-display",
  renewal_notice_days: "contract-renewal-notice",
  renewal_notes: "contract-renewal-notes",
  terms: "contract-terms",
};

const contractTermFieldSuffixes: Record<string, string> = {
  effective_start: "start-display",
  effective_end: "end-display",
  billing_cycle: "cycle",
  quantity: "quantity",
  unit_price: "unit-price",
  entered_amount: "amount",
  amount_includes_vat: "vat-included",
  vat_rate: "vat",
  auto_renew: "auto-renew",
};

function contractValidationFieldId(field: string): string {
  const termField = /^terms\.(\d+)(?:\.([a-z_]+))?$/.exec(field);
  if (termField) {
    const suffix = termField[2] ? contractTermFieldSuffixes[termField[2]] : undefined;
    return suffix ? `term-${termField[1]}-${suffix}` : `contract-term-${termField[1]}`;
  }

  return contractFieldIds[field] ?? "contract-form";
}

function contractValidationSuggestion(field: string): string {
  const segments = field.split(".");
  const name = segments[segments.length - 1] ?? field;
  const suggestions: Record<string, string> = {
    vendor_id: "Seleziona un Fornitore disponibile.",
    cost_center_id: "Seleziona un Centro di costo disponibile.",
    project_id: "Seleziona un Progetto disponibile.",
    title: "Inserisci un titolo valido.",
    description: "Controlla la descrizione inserita.",
    active: "Controlla questa opzione.",
    renewal_date: "Inserisci una data di rinnovo valida.",
    renewal_notice_days: "Inserisci un numero di giorni uguale o maggiore di zero.",
    renewal_notes: "Controlla le note inserite.",
    effective_start: "Inserisci una data iniziale valida.",
    effective_end: "Inserisci una data finale non precedente a quella iniziale.",
    billing_cycle: "Seleziona un ciclo di fatturazione valido.",
    quantity: "Inserisci una quantità valida con massimo 2 decimali.",
    unit_price: "Inserisci un prezzo valido con massimo 2 decimali.",
    entered_amount: "Inserisci un importo valido con massimo 2 decimali.",
    amount_includes_vat: "Controlla questa opzione.",
    vat_rate: "Inserisci un'IVA valida con massimo 2 decimali.",
    auto_renew: "Controlla questa opzione.",
  };
  if (suggestions[name]) return suggestions[name];
  if (field === "terms") return "Aggiungi almeno un termine contrattuale.";
  if (field.startsWith("terms.")) return "Controlla i dati di questo termine.";

  return "Controlla il valore inserito.";
}

export default function ContractForm({ contract = null, defaultVatRate = null, canSubmit, submitting = false, error = null, onSubmit }: { contract?: Contract | null; defaultVatRate?: string | null; canSubmit: boolean; submitting?: boolean; error?: ApiError | null; onSubmit: (input: ContractWrite | ContractUpdate) => Promise<void> }) {
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
  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
  const [focusRequest, setFocusRequest] = useState<InvalidFieldFocusRequest | null>(null);
  const [vendors, setVendors] = useState<ContractLookupOption[]>([]);
  const [costCenters, setCostCenters] = useState<ContractLookupOption[]>([]);
  const [projects, setProjects] = useState<ProjectLookupOption[]>([]);
  const [lookupError, setLookupError] = useState<string | null>(null);

  useInvalidFieldFocus(focusRequest);

  useEffect(() => {
    if (error?.handledStatus !== 422) return;
    const next = Object.fromEntries(Object.keys(error.fields).map((field) => [field, contractValidationSuggestion(field)]));
    setValidationErrors(next);
    const firstField = Object.keys(next)[0];
    if (firstField) setFocusRequest({ id: contractValidationFieldId(firstField) });
  }, [error]);

  useEffect(() => {
    if (!canSubmit) return;
    let mounted = true;
    void Promise.all([listContractVendors(), listContractCostCenters(), listProjectOptions()]).then(([vendorResponse, costCenterResponse, projectResponse]) => { if (mounted) { setVendors(vendorResponse.data); setCostCenters(costCenterResponse.data); setProjects(projectResponse); } }).catch((requestError: unknown) => { if (mounted) setLookupError(requestError instanceof Error ? requestError.message : "Impossibile caricare i dati di supporto."); });
    return () => { mounted = false; };
  }, [canSubmit]);

  function clearValidationFields(fields: string[]) {
    setValidationMessage(null);
    setValidationErrors((current) => {
      const next = { ...current };
      fields.forEach((field) => {
        Object.keys(next).forEach((candidate) => {
          if (candidate === field || candidate.startsWith(`${field}.`)) delete next[candidate];
        });
      });
      return next;
    });
  }

  function showValidationErrors(next: Record<string, string>) {
    setValidationErrors(next);
    setValidationMessage("Correggi il campo evidenziato.");
    const firstField = Object.keys(next)[0];
    if (firstField) setFocusRequest({ id: contractValidationFieldId(firstField) });
  }

  const fieldError = (field: string) => validationErrors[field];
  const handleRenewalDate = (_: unknown, value: string) => {
    setRenewalDate(value);
    clearValidationFields(["renewal_date"]);
  };

  const submit = async () => {
    setValidationMessage(null);
    setValidationErrors({});
    const parsedVendorId = Number.parseInt(vendorId, 10);
    const parsedCostCenterId = Number.parseInt(costCenterId, 10);
    const headerErrors: Record<string, string> = {};
    if (!vendorId || !Number.isInteger(parsedVendorId)) headerErrors.vendor_id = "Seleziona un Fornitore.";
    if (!costCenterId || !Number.isInteger(parsedCostCenterId)) headerErrors.cost_center_id = "Seleziona un Centro di costo.";
    if (!title.trim()) headerErrors.title = "Inserisci un titolo.";
    if (terms.length === 0) headerErrors.terms = "Aggiungi almeno un termine contrattuale.";
    const parsedRenewalNoticeDays = renewalNoticeDays === "" ? null : Number.parseInt(renewalNoticeDays, 10);
    if (parsedRenewalNoticeDays !== null && (!Number.isInteger(parsedRenewalNoticeDays) || parsedRenewalNoticeDays < 0)) {
      headerErrors.renewal_notice_days = "Inserisci un numero di giorni uguale o maggiore di zero.";
    }

    const termErrors: Record<string, string> = {};
    const preparedTerms = terms.map((term, index) => {
      if (!term.effective_start) termErrors[`terms.${index}.effective_start`] = "Inserisci la data iniziale.";
      if (!term.effective_end) termErrors[`terms.${index}.effective_end`] = "Inserisci la data finale.";
      const normalize = (field: "entered_amount" | "vat_rate" | "quantity" | "unit_price", value: string | null | undefined): string | null => {
        if (value === null || value === undefined || value === "") return null;
        try {
          return normalizeDecimalInput(value, 2);
        } catch {
          termErrors[`terms.${index}.${field}`] = contractValidationSuggestion(field);
          return value;
        }
      };
      return {
        ...term,
        entered_amount: normalize("entered_amount", term.entered_amount) ?? "",
        vat_rate: normalize("vat_rate", term.vat_rate),
        quantity: normalize("quantity", term.quantity),
        unit_price: normalize("unit_price", term.unit_price),
      };
    });
    const nextErrors = { ...headerErrors, ...termErrors };
    if (Object.keys(nextErrors).length > 0) {
      showValidationErrors(nextErrors);
      return;
    }

    const input: ContractWrite = { vendor_id: parsedVendorId, cost_center_id: parsedCostCenterId, ...(projectId ? { project_id: Number.parseInt(projectId, 10) } : {}), title: title.trim(), description: description.trim() || undefined, active, renewal_date: renewalDate || null, renewal_notice_days: parsedRenewalNoticeDays, renewal_notes: renewalNotes.trim() || undefined, terms: preparedTerms };
    await onSubmit(contract ? { ...input, lock_version: contract.lock_version } : input);
  };
  const disabled = !canSubmit || submitting;

  return <form id="contract-form" tabIndex={-1} className="space-y-6 focus:outline-hidden" onSubmit={(event) => { event.preventDefault(); void submit(); }}>
    {error ? <Alert variant="error" title={error.handledStatus === 422 ? "Controlla i dati" : "Salvataggio non riuscito"} message={`${error.message}${error.handledStatus === 422 ? " Correggi il campo evidenziato." : ""}${error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : ""}`} /> : null}
    {lookupError ? <Alert variant="warning" title="Dati di supporto non disponibili" message={lookupError} /> : null}
    {validationMessage ? <Alert variant="warning" title="Controlla i campi" message={validationMessage} /> : null}
    <ComponentCard title="Dati del Contratto"><div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div><Label htmlFor="contract-vendor">Fornitore</Label><Select id="contract-vendor" options={vendors.map((vendor) => ({ value: String(vendor.id), label: `${vendor.name}${vendor.active === false ? " (inattivo)" : ""}` }))} placeholder={vendors.length === 0 ? "Caricamento fornitori…" : "Seleziona un fornitore"} value={vendorId} onChange={(value) => { setVendorId(value); clearValidationFields(["vendor_id"]); }} disabled={disabled} error={Boolean(fieldError("vendor_id"))} hint={fieldError("vendor_id")} /></div>
      <div><Label htmlFor="contract-cost-center">Centro di costo</Label><Select id="contract-cost-center" options={costCenters.map((item) => ({ value: String(item.id), label: `${item.name}${item.active === false ? " (inattivo)" : ""}` }))} placeholder={costCenters.length === 0 ? "Caricamento centri di costo…" : "Seleziona un centro di costo"} value={costCenterId} onChange={(value) => { setCostCenterId(value); clearValidationFields(["cost_center_id"]); }} disabled={disabled} error={Boolean(fieldError("cost_center_id"))} hint={fieldError("cost_center_id")} /></div>
      <div><Label htmlFor="contract-project">Progetto (opzionale)</Label><Select id="contract-project" options={projects.map((item) => ({ value: String(item.id), label: item.title }))} placeholder="Nessun progetto" allowEmpty value={projectId} onChange={(value) => { setProjectId(value); clearValidationFields(["project_id"]); }} disabled={disabled} error={Boolean(fieldError("project_id"))} hint={fieldError("project_id")} /></div>
      <div className="md:col-span-2"><Label htmlFor="contract-title">Titolo</Label><InputField id="contract-title" value={title} onChange={(event) => { setTitle(event.target.value); clearValidationFields(["title"]); }} disabled={disabled} error={Boolean(fieldError("title"))} hint={fieldError("title")} /></div>
      <div className="md:col-span-2"><Label htmlFor="contract-description">Descrizione</Label><TextArea id="contract-description" value={description} onChange={(value) => { setDescription(value); clearValidationFields(["description"]); }} disabled={disabled} placeholder="Descrizione opzionale" error={Boolean(fieldError("description"))} hint={fieldError("description")} /></div>
    </div></ComponentCard>
    <ComponentCard title="Rinnovo"><div className="grid grid-cols-1 gap-4 md:grid-cols-2"><DatePicker id="contract-renewal-date" label="Data di rinnovo" placeholder="Seleziona la data" defaultDate={renewalDate || undefined} onChange={handleRenewalDate} disabled={disabled} error={Boolean(fieldError("renewal_date"))} hint={fieldError("renewal_date")} /><div><Label htmlFor="contract-renewal-notice">Giorni di preavviso</Label><InputField id="contract-renewal-notice" type="number" min="0" value={renewalNoticeDays} onChange={(event) => { setRenewalNoticeDays(event.target.value); clearValidationFields(["renewal_notice_days"]); }} disabled={disabled} error={Boolean(fieldError("renewal_notice_days"))} hint={fieldError("renewal_notice_days")} /></div><div className="md:col-span-2"><Label htmlFor="contract-renewal-notes">Note sul rinnovo</Label><InputField id="contract-renewal-notes" value={renewalNotes} onChange={(event) => { setRenewalNotes(event.target.value); clearValidationFields(["renewal_notes"]); }} disabled={disabled} placeholder="Note opzionali" error={Boolean(fieldError("renewal_notes"))} hint={fieldError("renewal_notes")} /></div><div className="md:col-span-2"><Checkbox id="contract-active" label="Contratto attivo" checked={active} onChange={(value) => { setActive(value); clearValidationFields(["active"]); }} disabled={disabled} error={Boolean(fieldError("active"))} hint={fieldError("active")} /></div></div></ComponentCard>
    <ContractTermsEditor terms={terms} onChange={setTerms} defaultVatRate={defaultVatRate} disabled={disabled} existingTerms={contract?.terms} validationErrors={validationErrors} onFieldChange={clearValidationFields} />
    <div className="flex justify-end"><Button disabled={disabled}>{submitting ? "Salvataggio…" : contract ? "Salva Modifiche" : "Crea Contratto"}</Button></div>
  </form>;
}
