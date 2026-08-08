import { useCallback, useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";

import { ApiError } from "../../api/client";
import {
  deleteContract,
  deleteContractTerm,
  deleteGeneratedExpense,
  generateContractOccurrence,
  getContract,
  getContractHistory,
  resumeAndGenerateContractOccurrence,
  resumeContractOccurrence,
  suppressContractOccurrence,
  synchronizeContract,
  type Contract,
  type ContractOccurrence,
  type ContractRevision,
  type GeneratedExpense,
} from "../../api/contracts";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ContractHistoryTable from "../../components/contracts/ContractHistoryTable";
import ContractOccurrenceTable from "../../components/contracts/ContractOccurrenceTable";
import InputField from "../../components/form/input/InputField";
import Label from "../../components/form/Label";
import Checkbox from "../../components/form/input/Checkbox";
import Alert from "../../components/ui/alert/Alert";
import Badge from "../../components/ui/badge/Badge";
import Button from "../../components/ui/button/Button";
import { Dropdown } from "../../components/ui/dropdown/Dropdown";
import { DropdownItem } from "../../components/ui/dropdown/DropdownItem";
import { Modal } from "../../components/ui/modal";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../../components/ui/table";
import { useApplicationContext } from "../../context/ApplicationContext";

type ModalKind = "delete-contract" | "generate" | "suppress" | "delete-term" | "delete-expense" | null;

export default function ContractDetail() {
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const { contractId: contractIdParam } = useParams<{ contractId: string }>();
  const navigate = useNavigate();
  const [contract, setContract] = useState<Contract | null>(null);
  const [history, setHistory] = useState<ContractRevision[]>([]);
  const [loading, setLoading] = useState(false);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [error, setError] = useState<ApiError | null>(null);
  const [actionError, setActionError] = useState<ApiError | null>(null);
  const [modal, setModal] = useState<ModalKind>(null);
  const [targetOccurrence, setTargetOccurrence] = useState<ContractOccurrence | null>(null);
  const [targetTermId, setTargetTermId] = useState<number | null>(null);
  const [targetGeneratedExpense, setTargetGeneratedExpense] = useState<GeneratedExpense | null>(null);
  const [reason, setReason] = useState("");
  const [generationYear, setGenerationYear] = useState("");
  const [allowRegeneration, setAllowRegeneration] = useState(false);
  const [actionsOpen, setActionsOpen] = useState(false);
  const tenantId = data?.tenant?.id ?? null;
  const contractId = contractIdParam && /^\d+$/.test(contractIdParam) ? Number(contractIdParam) : null;
  const canView = hasAbility("contract.view");
  const canEdit = hasAbility("contract.update");

  const load = useCallback(async () => {
    if (tenantId === null || contractId === null || !canView) return;
    setLoading(true);
    setError(null);
    try { setContract(await getContract(contractId)); } catch (requestError) { setContract(null); setError(ApiError.from(requestError)); } finally { setLoading(false); }
  }, [canView, contractId, tenantId]);

  useEffect(() => { if (!contextLoading) void load(); }, [contextLoading, load]);

  const runAction = async (key: string, action: () => Promise<void>) => {
    setBusyKey(key);
    setActionError(null);
    try { await action(); await load(); } catch (requestError) { setActionError(ApiError.from(requestError)); } finally { setBusyKey(null); }
  };

  const loadHistory = async () => {
    if (contractId === null || !hasAbility("contract.view-revisions")) return;
    setHistoryLoading(true);
    try { const response = await getContractHistory(contractId, { per_page: 100 }); setHistory(response.data); } catch (requestError) { setActionError(ApiError.from(requestError)); } finally { setHistoryLoading(false); }
  };

  const closeModal = () => { setModal(null); setReason(""); setTargetOccurrence(null); setTargetTermId(null); setTargetGeneratedExpense(null); };

  const confirmModal = async () => {
    if (!contract) return;
    if (modal === "delete-contract") {
      await runAction("delete-contract", async () => { await deleteContract(contract.id, { lock_version: contract.lock_version, deletion_reason: reason || undefined }); navigate("/contracts", { replace: true }); });
      closeModal();
    } else if (modal === "generate") {
      const year = Number(generationYear);
      if (!Number.isInteger(year) || year < 1000 || year > 9999) { setActionError(new ApiError({ message: "Enter a valid planning year." })); return; }
      await runAction("generate", async () => { await generateContractOccurrence(contract.id, year); });
      closeModal();
    } else if (modal === "suppress" && targetOccurrence) {
      await runAction(`occurrence:${targetOccurrence.source_key}`, async () => { await suppressContractOccurrence(contract.id, targetOccurrence.source_key, { reason: reason || undefined }); });
      closeModal();
    } else if (modal === "delete-term" && targetTermId !== null) {
      const term = contract.terms.find((candidate) => candidate.id === targetTermId);
      if (!term) return;
      await runAction(`term:${targetTermId}`, async () => { await deleteContractTerm(contract.id, targetTermId, { lock_version: term.lock_version, deletion_reason: reason || undefined }); });
      closeModal();
    } else if (modal === "delete-expense" && targetGeneratedExpense) {
      await runAction(`expense:${targetGeneratedExpense.id}`, async () => { await deleteGeneratedExpense(contract.id, targetGeneratedExpense.id, { lock_version: targetGeneratedExpense.lock_version, allow_regeneration: allowRegeneration }); });
      closeModal();
    }
  };

  const canGenerate = hasAbility("contract.generate-occurrence");
  const canSuppress = hasAbility("contract.suppress-generation");
  const canResume = hasAbility("contract.resume-generation");
  const canDelete = hasAbility("contract.delete");
  const canDeleteGenerated = hasAbility("expense.delete");

  let body: React.ReactNode;
  if (contextLoading) body = <Alert variant="info" title="Loading context" message="Checking tenant and contract ability." />;
  else if (tenantId === null) body = <Alert variant="warning" title="Tenant required" message="Enter a tenant before opening a contract." />;
  else if (contractId === null) body = <Alert variant="error" title="Invalid contract ID" message="The route contract ID is not valid." />;
  else if (!canView) body = <Alert variant="warning" title="Contract unavailable" message="The current context does not grant contract.view." />;
  else if (loading) body = <Alert variant="info" title="Loading contract" message="Requesting the contract detail." />;
  else if (!contract) body = <Alert variant="error" title="Contract request failed" message={error?.message ?? "The contract detail could not be loaded."} />;
  else body = (
    <div className="space-y-6">
      {error ? <Alert variant="error" title="Contract request failed" message={error.message} /> : null}
      {actionError ? <Alert variant="error" title="Contract operation failed" message={actionError.message} /> : null}
      <ComponentCard title={contract.title} desc={`Contract #${contract.id} · lock version ${contract.lock_version}`}>
        <div className="flex flex-wrap items-start justify-between gap-4"><div className="flex flex-wrap items-center gap-3"><Badge color={contract.active ? "success" : "light"}>{contract.active ? "Active" : "Inactive"}</Badge><span className="text-sm text-gray-500">{contract.vendor?.name ?? `Vendor #${contract.vendor_id}`} · {contract.cost_center?.name ?? `Cost center #${contract.cost_center_id}`}</span></div><div className="flex items-center gap-2"><Link to="/contracts" className="text-sm font-medium text-gray-600 hover:text-brand-500 dark:text-gray-300">Back</Link>{canEdit ? <Link to={`/contracts/${contract.id}/edit`} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Edit</Link> : null}{canGenerate ? <div className="relative"><button type="button" onClick={() => setActionsOpen((open) => !open)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300" aria-expanded={actionsOpen}>Actions</button><Dropdown isOpen={actionsOpen} onClose={() => setActionsOpen(false)} className="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"><DropdownItem onClick={() => { setActionsOpen(false); setModal("generate"); }} baseClassName="block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">Generate occurrence</DropdownItem><DropdownItem onClick={() => { setActionsOpen(false); void runAction("synchronize", async () => { await synchronizeContract(contract.id); }); }} baseClassName="block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">Synchronize occurrences</DropdownItem></Dropdown></div> : null}{canDelete ? <Button size="sm" variant="outline" onClick={() => setModal("delete-contract")}>Delete</Button> : null}</div></div>
        <div className="grid gap-4 md:grid-cols-3"><div><p className="text-xs uppercase text-gray-500">Description</p><p className="mt-1 text-sm text-gray-800 dark:text-gray-200">{contract.description ?? "—"}</p></div><div><p className="text-xs uppercase text-gray-500">Renewal</p><p className="mt-1 text-sm text-gray-800 dark:text-gray-200">{contract.renewal_date ?? "—"}{contract.renewal_notice_days === null ? "" : ` · ${contract.renewal_notice_days} days notice`}</p></div><div><p className="text-xs uppercase text-gray-500">Official basis</p><p className="mt-1 text-sm text-gray-800 dark:text-gray-200">{contract.official_basis ?? "Server contract resource"} · {contract.currency ?? ""}</p></div></div>
      </ComponentCard>
      <ComponentCard title="Terms"><div className="max-w-full overflow-x-auto"><Table><TableHeader className="border-y border-gray-100 dark:border-gray-800"><TableRow>{(["Key", "Dates", "Cycle", "Entered", "Server totals", "Actions"] as const).map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap py-3 text-start text-theme-xs font-medium text-gray-500">{heading}</TableCell>)}</TableRow></TableHeader><TableBody className="divide-y divide-gray-100 dark:divide-gray-800">{contract.terms.map((term) => <TableRow key={term.id ?? term.local_key}><TableCell className="py-3 text-sm text-gray-800 dark:text-white/90">{term.local_key}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{term.effective_start} → {term.effective_end}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{term.billing_cycle}</TableCell><TableCell className="py-3 text-sm text-gray-600 dark:text-gray-300">{term.entered_amount} {term.currency ?? ""}</TableCell><TableCell className="whitespace-nowrap py-3 text-sm text-gray-600 dark:text-gray-300">net {term.net} · VAT {term.vat} · gross {term.gross}</TableCell><TableCell className="py-3">{canEdit && term.id !== null ? <Button size="sm" variant="outline" onClick={() => { setTargetTermId(term.id); setModal("delete-term"); }}>Delete</Button> : null}</TableCell></TableRow>)}</TableBody></Table></div></ComponentCard>
      <ComponentCard title="Generation"><ContractOccurrenceTable occurrences={contract.occurrences} generatedExpenses={contract.generated_expenses} canGenerate={canGenerate} canSuppress={canSuppress} canResume={canResume} canDeleteGenerated={canDeleteGenerated} busyKey={busyKey} onSynchronize={() => void runAction("synchronize", async () => { await synchronizeContract(contract.id); })} onSuppress={(occurrence) => { setTargetOccurrence(occurrence); setModal("suppress"); }} onResume={(occurrence) => void runAction(`occurrence:${occurrence.source_key}`, async () => { await resumeContractOccurrence(contract.id, occurrence.source_key); })} onResumeAndGenerate={(occurrence) => void runAction(`occurrence:${occurrence.source_key}`, async () => { await resumeAndGenerateContractOccurrence(contract.id, occurrence.source_key); })} onDeleteGenerated={(expense) => { setTargetGeneratedExpense(expense); setAllowRegeneration(false); setModal("delete-expense"); }} /></ComponentCard>
      <ComponentCard title="Revision history"><div className="mb-4 flex justify-end">{hasAbility("contract.view-revisions") ? <Button size="sm" variant="outline" onClick={() => void loadHistory()} disabled={historyLoading}>{historyLoading ? "Loading…" : "Load history"}</Button> : null}</div><ContractHistoryTable revisions={history} /></ComponentCard>
    </div>
  );

  return <><PageMeta title="Contract detail | Master Plan IT" description="Tenant contract detail" /><PageBreadcrumb pageTitle="Contract detail" />{body}<Modal isOpen={modal !== null} onClose={closeModal} className="max-w-lg p-6">{modal === "delete-contract" ? <><h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Delete contract?</h2><p className="mt-2 text-sm text-gray-500">The contract identity is terminal. Linked generated expenses remain governed by the API.</p><Label htmlFor="delete-contract-reason">Reason (optional)</Label><InputField id="delete-contract-reason" value={reason} onChange={(event) => setReason(event.target.value)} /><ModalButtons busy={busyKey === "delete-contract"} onCancel={closeModal} onConfirm={() => void confirmModal()} label="Confirm delete" /></> : null}{modal === "generate" ? <><h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Generate occurrence</h2><p className="mt-2 text-sm text-gray-500">The year is sent to the documented generation endpoint; amounts come from Laravel.</p><Label htmlFor="generation-year">Planning year</Label><InputField id="generation-year" type="number" min="1000" max="9999" value={generationYear} onChange={(event) => setGenerationYear(event.target.value)} /><ModalButtons busy={busyKey === "generate"} onCancel={closeModal} onConfirm={() => void confirmModal()} label="Generate" /></> : null}{modal === "suppress" ? <><h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Suppress occurrence?</h2><Label htmlFor="suppress-reason">Reason (optional)</Label><InputField id="suppress-reason" value={reason} onChange={(event) => setReason(event.target.value)} /><ModalButtons busy={busyKey?.startsWith("occurrence:") ?? false} onCancel={closeModal} onConfirm={() => void confirmModal()} label="Suppress" /></> : null}{modal === "delete-term" ? <><h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Delete term?</h2><Label htmlFor="delete-term-reason">Reason (optional)</Label><InputField id="delete-term-reason" value={reason} onChange={(event) => setReason(event.target.value)} /><ModalButtons busy={busyKey?.startsWith("term:") ?? false} onCancel={closeModal} onConfirm={() => void confirmModal()} label="Delete term" /></> : null}{modal === "delete-expense" ? <><h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Delete generated expense?</h2><p className="mt-2 text-sm text-gray-500">Choose whether the occurrence may be generated again.</p><Checkbox label="Allow regeneration" checked={allowRegeneration} onChange={setAllowRegeneration} /><ModalButtons busy={busyKey?.startsWith("expense:") ?? false} onCancel={closeModal} onConfirm={() => void confirmModal()} label="Delete generated expense" /></> : null}</Modal></>;
}

function ModalButtons({ busy, onCancel, onConfirm, label }: { busy: boolean; onCancel: () => void; onConfirm: () => void; label: string }) {
  return <div className="mt-6 flex justify-end gap-3"><Button size="sm" variant="outline" onClick={onCancel}>Cancel</Button><Button size="sm" onClick={onConfirm} disabled={busy}>{busy ? "Working…" : label}</Button></div>;
}
