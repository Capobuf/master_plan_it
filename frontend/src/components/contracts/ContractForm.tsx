import { useCallback, useState } from "react";

import type {
  Contract,
  ContractTermInput,
  ContractUpdate,
  ContractWrite,
} from "../../api/contracts";
import Checkbox from "../form/input/Checkbox";
import DatePicker from "../form/date-picker";
import InputField from "../form/input/InputField";
import Label from "../form/Label";
import Button from "../ui/button/Button";
import Alert from "../ui/alert/Alert";
import ContractTermsEditor from "./ContractTermsEditor";

interface ContractFormProps {
  contract?: Contract | null;
  canSubmit: boolean;
  submitting?: boolean;
  error?: string | null;
  onSubmit: (input: ContractWrite | ContractUpdate) => Promise<void>;
}

function toTermInput(term: Contract["terms"][number]): ContractTermInput {
  return {
    id: term.id ?? undefined,
    local_key: term.local_key,
    effective_start: term.effective_start,
    effective_end: term.effective_end,
    billing_cycle: term.billing_cycle,
    quantity: term.quantity,
    unit_price: term.unit_price,
    entered_amount: term.entered_amount,
    amount_includes_vat: term.amount_includes_vat,
    vat_rate: term.vat_rate,
    auto_renew: term.auto_renew,
    lock_version: term.lock_version,
  };
}

export default function ContractForm({
  contract = null,
  canSubmit,
  submitting = false,
  error = null,
  onSubmit,
}: ContractFormProps) {
  const [vendorId, setVendorId] = useState(String(contract?.vendor_id ?? ""));
  const [costCenterId, setCostCenterId] = useState(String(contract?.cost_center_id ?? ""));
  const [title, setTitle] = useState(contract?.title ?? "");
  const [description, setDescription] = useState(contract?.description ?? "");
  const [active, setActive] = useState(contract?.active ?? true);
  const [renewalDate, setRenewalDate] = useState(contract?.renewal_date ?? "");
  const [renewalNoticeDays, setRenewalNoticeDays] = useState(String(contract?.renewal_notice_days ?? ""));
  const [renewalNotes, setRenewalNotes] = useState(contract?.renewal_notes ?? "");
  const [terms, setTerms] = useState<ContractTermInput[]>(contract?.terms.map(toTermInput) ?? []);
  const [validationMessage, setValidationMessage] = useState<string | null>(null);

  const handleRenewalDate = useCallback((_: unknown, dateString: string) => {
    setRenewalDate(dateString);
  }, []);

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setValidationMessage(null);
    const numericVendorId = Number(vendorId);
    const numericCostCenterId = Number(costCenterId);
    if (!Number.isInteger(numericVendorId) || numericVendorId < 1 || !Number.isInteger(numericCostCenterId) || numericCostCenterId < 1) {
      setValidationMessage("Vendor and cost center IDs must be positive integers.");
      return;
    }
    if (!title.trim() || terms.length === 0) {
      setValidationMessage("A title and at least one complete term are required.");
      return;
    }

    const input: ContractWrite = {
      vendor_id: numericVendorId,
      cost_center_id: numericCostCenterId,
      title: title.trim(),
      description: description || undefined,
      active,
      renewal_date: renewalDate || null,
      renewal_notice_days: renewalNoticeDays === "" ? null : Number(renewalNoticeDays),
      renewal_notes: renewalNotes || undefined,
      terms,
    };

    await onSubmit(contract ? { ...input, lock_version: contract.lock_version } : input);
  };

  const disabled = !canSubmit || submitting;

  return (
    <form className="space-y-6" onSubmit={(event) => void submit(event)}>
      {error ? <Alert variant="error" title="Contract request failed" message={error} /> : null}
      {validationMessage ? <Alert variant="warning" title="Check the contract fields" message={validationMessage} /> : null}
      <div className="grid gap-4 md:grid-cols-2">
        <div><Label htmlFor="contract-vendor">Vendor ID</Label><InputField id="contract-vendor" type="number" min="1" value={vendorId} onChange={(event) => setVendorId(event.target.value)} disabled={disabled} /></div>
        <div><Label htmlFor="contract-cost-center">Cost center ID</Label><InputField id="contract-cost-center" type="number" min="1" value={costCenterId} onChange={(event) => setCostCenterId(event.target.value)} disabled={disabled} /></div>
        <div className="md:col-span-2"><Label htmlFor="contract-title">Title</Label><InputField id="contract-title" value={title} onChange={(event) => setTitle(event.target.value)} disabled={disabled} /></div>
        <div className="md:col-span-2"><Label htmlFor="contract-description">Description</Label><textarea id="contract-description" value={description} onChange={(event) => setDescription(event.target.value)} disabled={disabled} className="min-h-24 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" /></div>
        <div><DatePicker id="contract-renewal-date" label="Renewal date" placeholder="Select a renewal date" defaultDate={renewalDate || undefined} onChange={handleRenewalDate} /></div>
        <div><Label htmlFor="contract-renewal-notice">Renewal notice days</Label><InputField id="contract-renewal-notice" type="number" min="0" value={renewalNoticeDays} onChange={(event) => setRenewalNoticeDays(event.target.value)} disabled={disabled} /></div>
        <div className="md:col-span-2"><Label htmlFor="contract-renewal-notes">Renewal notes</Label><InputField id="contract-renewal-notes" value={renewalNotes} onChange={(event) => setRenewalNotes(event.target.value)} disabled={disabled} /></div>
      </div>
      <Checkbox label="Contract is active" checked={active} onChange={setActive} disabled={disabled} />
      <ContractTermsEditor terms={terms} onChange={setTerms} disabled={disabled} existingTerms={contract?.terms} />
      <div className="flex justify-end"><Button disabled={disabled}>{submitting ? "Saving…" : contract ? "Save contract" : "Create contract"}</Button></div>
    </form>
  );
}
