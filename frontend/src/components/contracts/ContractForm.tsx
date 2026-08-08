import { useCallback, useEffect, useState } from "react";

import type {
  Contract,
  ContractTermInput,
  ContractUpdate,
  ContractWrite,
} from "../../api/contracts";
import {
  listContractCostCenters,
  listContractVendors,
  type ContractLookupOption,
} from "../../api/contracts";
import Checkbox from "../form/input/Checkbox";
import DatePicker from "../form/date-picker";
import InputField from "../form/input/InputField";
import TextArea from "../form/input/TextArea";
import Label from "../form/Label";
import Select from "../form/Select";
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
  const [vendors, setVendors] = useState<ContractLookupOption[]>([]);
  const [costCenters, setCostCenters] = useState<ContractLookupOption[]>([]);
  const [lookupError, setLookupError] = useState<string | null>(null);

  const handleRenewalDate = useCallback((_: unknown, dateString: string) => {
    setRenewalDate(dateString);
  }, []);

  useEffect(() => {
    if (!canSubmit) return;
    let active = true;
    void Promise.all([listContractVendors(), listContractCostCenters()])
      .then(([vendorResponse, costCenterResponse]) => {
        if (active) {
          setVendors(vendorResponse.data);
          setCostCenters(costCenterResponse.data);
        }
      })
      .catch((requestError: unknown) => {
        if (active) setLookupError(requestError instanceof Error ? requestError.message : "Unable to load contract lookups.");
      });
    return () => { active = false; };
  }, [canSubmit]);

  const submit = async () => {
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
    <>
      <form className="space-y-6" onSubmit={(event) => { event.preventDefault(); void submit(); }}>
      {error ? <Alert variant="error" title="Contract request failed" message={error} /> : null}
      {lookupError ? <Alert variant="warning" title="Lookup request failed" message={`${lookupError} Vendor and cost-center lists are required for selection.`} /> : null}
      {validationMessage ? <Alert variant="warning" title="Check the contract fields" message={validationMessage} /> : null}
      <div className="grid gap-4 md:grid-cols-2">
        <div><Label>Vendor</Label><Select options={vendors.map((vendor) => ({ value: String(vendor.id), label: `${vendor.name}${vendor.active === false ? " (inactive)" : ""}` }))} placeholder={vendors.length === 0 ? "Loading vendors…" : "Select a vendor"} defaultValue={vendorId} onChange={setVendorId} /></div>
        <div><Label>Cost center</Label><Select options={costCenters.map((costCenter) => ({ value: String(costCenter.id), label: `${costCenter.name}${costCenter.active === false ? " (inactive)" : ""}` }))} placeholder={costCenters.length === 0 ? "Loading cost centers…" : "Select a cost center"} defaultValue={costCenterId} onChange={setCostCenterId} /></div>
        <div className="md:col-span-2"><Label htmlFor="contract-title">Title</Label><InputField id="contract-title" value={title} onChange={(event) => setTitle(event.target.value)} disabled={disabled} /></div>
        <div className="md:col-span-2"><Label>Description</Label><TextArea value={description} onChange={setDescription} disabled={disabled} /></div>
        <div><DatePicker id="contract-renewal-date" label="Renewal date" placeholder="Select a renewal date" defaultDate={renewalDate || undefined} onChange={handleRenewalDate} /></div>
        <div><Label htmlFor="contract-renewal-notice">Renewal notice days</Label><InputField id="contract-renewal-notice" type="number" min="0" value={renewalNoticeDays} onChange={(event) => setRenewalNoticeDays(event.target.value)} disabled={disabled} /></div>
        <div className="md:col-span-2"><Label htmlFor="contract-renewal-notes">Renewal notes</Label><InputField id="contract-renewal-notes" value={renewalNotes} onChange={(event) => setRenewalNotes(event.target.value)} disabled={disabled} /></div>
      </div>
      <Checkbox label="Contract is active" checked={active} onChange={setActive} disabled={disabled} />
      </form>
      <ContractTermsEditor terms={terms} onChange={setTerms} disabled={disabled} existingTerms={contract?.terms} />
      <div className="flex justify-end"><Button onClick={() => void submit()} disabled={disabled}>{submitting ? "Saving…" : contract ? "Save contract" : "Create contract"}</Button></div>
    </>
  );
}
