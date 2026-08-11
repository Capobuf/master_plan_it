import { useCallback, useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";
import { ApiError } from "../../api/client";
import { deleteContract, deleteContractTerm, deleteGeneratedExpense, generateContractOccurrence, getContract, getContractHistory, getContractRevision, restoreContractRevision, resumeAndGenerateContractOccurrence, resumeContractOccurrence, suppressContractOccurrence, synchronizeContract, type Contract, type ContractOccurrence, type ContractRevision, type GeneratedExpense } from "../../api/contracts";
import ComponentCard from "../../components/common/ComponentCard";
import AttachmentPanel from "../../components/attachments/AttachmentPanel";
import IconButton from "../../components/common/IconButton";
import ObjectTabs from "../../components/common/ObjectTabs";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ContractOccurrenceTable from "../../components/contracts/ContractOccurrenceTable";
import Checkbox from "../../components/form/input/Checkbox";
import InputField from "../../components/form/input/InputField";
import Label from "../../components/form/Label";
import RevisionHistoryPanel from "../../components/revisions/RevisionHistoryPanel";
import Alert from "../../components/ui/alert/Alert";
import Badge from "../../components/ui/badge/Badge";
import Button from "../../components/ui/button/Button";
import { Dropdown } from "../../components/ui/dropdown/Dropdown";
import { DropdownItem } from "../../components/ui/dropdown/DropdownItem";
import { Modal } from "../../components/ui/modal";
import { useApplicationContext } from "../../context/ApplicationContext";
import { MoreDotIcon } from "../../icons";
import { routes } from "../../navigation/routes";
import { formatDate, formatMoney, formatPercentage } from "../../presentation/formatters";
import { domainLabel } from "../../presentation/labels";

type ModalKind = "delete-contract" | "generate" | "suppress" | "delete-term" | "delete-expense" | null;

export default function ContractDetail() {
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const { contractId: contractIdParam } = useParams<{ contractId: string }>();
  const navigate = useNavigate();
  const [contract, setContract] = useState<Contract | null>(null);
  const [history, setHistory] = useState<ContractRevision[]>([]);
  const [loading, setLoading] = useState(false);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<"details" | "attachments" | "history">("details");
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
  const contractId = contractIdParam && /^\d+$/.test(contractIdParam) ? Number.parseInt(contractIdParam, 10) : null;
  const canView = hasAbility("contract.view");

  const load = useCallback(async () => {
    if (tenantId === null || contractId === null || !canView) return;
    setLoading(true); setError(null);
    try { setContract(await getContract(contractId)); } catch (requestError) { setContract(null); setError(ApiError.from(requestError)); } finally { setLoading(false); }
  }, [canView, contractId, tenantId]);
  useEffect(() => { if (!contextLoading) void load(); }, [contextLoading, load]);
  useEffect(() => { setActiveTab("details"); setHistory([]); setHistoryError(null); }, [contractId, tenantId]);

  const runAction = async (key: string, action: () => Promise<void>) => {
    setBusyKey(key); setActionError(null);
    try { await action(); await load(); return true; } catch (requestError) { setActionError(ApiError.from(requestError)); return false; } finally { setBusyKey(null); }
  };
  const closeModal = () => { setModal(null); setReason(""); setTargetOccurrence(null); setTargetTermId(null); setTargetGeneratedExpense(null); };
  const loadHistory = async () => { if (contractId === null || !hasAbility("contract.view-revisions")) return; setHistoryLoading(true); setHistoryError(null); try { setHistory((await getContractHistory(contractId, { per_page: 10 })).data); } catch (requestError) { setHistoryError(ApiError.from(requestError).message); } finally { setHistoryLoading(false); } };
  const changeTab = (tab: "details" | "attachments" | "history") => { setActiveTab(tab); if (tab === "history" && history.length === 0 && !historyLoading) void loadHistory(); };
  const confirmModal = async () => {
    if (!contract) return;
    let completed = false;
    if (modal === "delete-contract") completed = await runAction("delete-contract", async () => { await deleteContract(contract.id, { lock_version: contract.lock_version, deletion_reason: reason.trim() || undefined }); navigate(routes.contratti, { replace: true }); });
    else if (modal === "generate") { const year=Number.parseInt(generationYear,10); if (!/^\d{4}$/.test(generationYear) || year < 1000) { setActionError(new ApiError({ message: "Inserisci un anno di pianificazione valido." })); return; } completed = await runAction("generate", async () => { await generateContractOccurrence(contract.id, year); }); }
    else if (modal === "suppress" && targetOccurrence) completed = await runAction(`occurrence:${targetOccurrence.source_key}`, async () => { await suppressContractOccurrence(contract.id, targetOccurrence.source_key, { reason: reason.trim() || undefined }); });
    else if (modal === "delete-term" && targetTermId !== null) { const term=contract.terms.find((item)=>item.id===targetTermId); if (term) completed = await runAction(`term:${targetTermId}`, async () => { await deleteContractTerm(contract.id, targetTermId, { lock_version: term.lock_version, deletion_reason: reason.trim() || undefined }); }); }
    else if (modal === "delete-expense" && targetGeneratedExpense) completed = await runAction(`expense:${targetGeneratedExpense.id}`, async () => { await deleteGeneratedExpense(contract.id, targetGeneratedExpense.id, { lock_version: targetGeneratedExpense.lock_version, allow_regeneration: allowRegeneration }); });
    if (completed) closeModal();
  };

  let body: React.ReactNode;
  if (contextLoading) body=<Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant in corso." />;
  else if (tenantId===null) body=<Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant prima di aprire il contratto." />;
  else if (contractId===null) body=<Alert variant="error" title="Contratto non valido" message="Il contratto richiesto non è valido." />;
  else if (!canView) body=<Alert variant="warning" title="Contratto non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare il contratto." />;
  else if (loading && !contract) body=<Alert variant="info" title="Caricamento del contratto" message="Recupero dei dati in corso." />;
  else if (!contract) body=<Alert variant="error" title="Caricamento non riuscito" message={error?.correlationId ? `${error.message} Riferimento tecnico: ${error.correlationId}` : error?.message ?? "Contratto non trovato."} />;
  else body=<div className="space-y-6">
    {actionError ? <Alert variant="error" title="Operazione non riuscita" message={actionError.correlationId ? `${actionError.message} Riferimento tecnico: ${actionError.correlationId}` : actionError.message} /> : null}
    <ComponentCard title={contract.title}>
      <div className="flex flex-wrap items-center justify-between gap-4"><div className="flex flex-wrap items-center gap-3"><Badge color={contract.active ? "success" : "light"}>{contract.active ? "Attivo" : "Inattivo"}</Badge><span className="text-sm text-gray-600 dark:text-gray-300">{contract.vendor?.name ?? "—"} · {contract.cost_center?.name ?? "—"}</span></div><div className="flex items-center gap-2">{hasAbility("contract.update") ? <Button size="sm" variant="outline" onClick={() => navigate(routes.modificaContratto(contract.id))}>Modifica</Button> : null}{hasAbility("contract.generate-occurrence") ? <div className="relative"><IconButton icon={MoreDotIcon} label="Altre azioni sul contratto" onClick={() => setActionsOpen((open)=>!open)} /><Dropdown isOpen={actionsOpen} onClose={() => setActionsOpen(false)} className="absolute right-0 z-20 mt-2 w-64 rounded-xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"><DropdownItem onClick={() => { setActionsOpen(false); setModal("generate"); }} baseClassName="block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">Genera ricorrenza</DropdownItem><DropdownItem onClick={() => { setActionsOpen(false); void runAction("synchronize", async()=>{await synchronizeContract(contract.id);}); }} baseClassName="block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">Sincronizza ricorrenze</DropdownItem></Dropdown></div> : null}{hasAbility("contract.delete") ? <Button size="sm" variant="outline" onClick={() => setModal("delete-contract")}>Elimina</Button> : null}</div></div>
      <dl className="grid grid-cols-1 gap-4 border-t border-gray-100 pt-5 md:grid-cols-2 lg:grid-cols-4 dark:border-gray-800"><div><dt className="text-sm text-gray-500 dark:text-gray-400">Fornitore</dt><dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{contract.vendor?.name ?? "—"}</dd></div><div><dt className="text-sm text-gray-500 dark:text-gray-400">Centro di costo</dt><dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{contract.cost_center?.name ?? "—"}</dd></div><div><dt className="text-sm text-gray-500 dark:text-gray-400">Data di rinnovo</dt><dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{formatDate(contract.renewal_date)}</dd></div><div><dt className="text-sm text-gray-500 dark:text-gray-400">Preavviso</dt><dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{contract.renewal_notice_days===null ? "—" : `${contract.renewal_notice_days} giorni`}</dd></div></dl>
      {contract.description ? <p className="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-300">{contract.description}</p> : null}{contract.renewal_notes ? <p className="text-sm text-gray-600 dark:text-gray-300"><span className="font-medium text-gray-800 dark:text-white/90">Note sul rinnovo:</span> {contract.renewal_notes}</p> : null}
    </ComponentCard>
    <ObjectTabs tabs={[{ key: "details" as const, label: "Dettagli" }, ...(hasAbility("attachment.view") ? [{ key: "attachments" as const, label: "Allegati" }] : []), ...(hasAbility("contract.view-revisions") ? [{ key: "history" as const, label: "Storico" }] : [])]} active={activeTab} onChange={changeTab} />
    {activeTab === "details" ? <div className="space-y-6">
    <ComponentCard title="Termini Contrattuali">{contract.terms.length===0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Nessun termine disponibile.</p> : <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">{contract.terms.map((term,index)=><article key={term.id ?? term.local_key} className="rounded-xl border border-gray-200 p-4 dark:border-gray-800"><div className="flex items-center justify-between gap-3"><h3 className="font-semibold text-gray-800 dark:text-white/90">Termine {index+1}</h3>{hasAbility("contract.update") && term.id!==null ? <Button size="sm" variant="outline" onClick={()=>{setTargetTermId(term.id);setModal("delete-term");}}>Rimuovi</Button>:null}</div><dl className="mt-4 grid grid-cols-2 gap-4 text-sm"><div><dt className="text-gray-500 dark:text-gray-400">Validità</dt><dd className="mt-1 text-gray-800 dark:text-white/90">{formatDate(term.effective_start)} – {formatDate(term.effective_end)}</dd></div><div><dt className="text-gray-500 dark:text-gray-400">Ciclo</dt><dd className="mt-1 text-gray-800 dark:text-white/90">{domainLabel(term.billing_cycle)}</dd></div><div><dt className="text-gray-500 dark:text-gray-400">Importo</dt><dd className="mt-1 text-gray-800 dark:text-white/90">{formatMoney(term.entered_amount,term.currency??"EUR")}</dd></div><div><dt className="text-gray-500 dark:text-gray-400">Aliquota IVA</dt><dd className="mt-1 text-gray-800 dark:text-white/90">{formatPercentage(term.vat_rate)}</dd></div><div><dt className="text-gray-500 dark:text-gray-400">Netto</dt><dd className="mt-1 text-gray-800 dark:text-white/90">{formatMoney(term.net,term.currency??"EUR")}</dd></div><div><dt className="text-gray-500 dark:text-gray-400">Lordo</dt><dd className="mt-1 font-medium text-gray-800 dark:text-white/90">{formatMoney(term.gross,term.currency??"EUR")}</dd></div></dl><div className="mt-4 flex flex-wrap gap-2">{term.amount_includes_vat ? <Badge color="info" size="sm">IVA inclusa</Badge>:null}{term.auto_renew ? <Badge color="success" size="sm">Rinnovo automatico</Badge>:null}</div></article>)}</div>}</ComponentCard>
    <ComponentCard title="Generazione e Spese"><ContractOccurrenceTable occurrences={contract.occurrences} generatedExpenses={contract.generated_expenses} canGenerate={hasAbility("contract.generate-occurrence")} canSuppress={hasAbility("contract.suppress-generation")} canResume={hasAbility("contract.resume-generation")} canDeleteGenerated={hasAbility("expense.delete")} busyKey={busyKey} onSynchronize={()=>void runAction("synchronize",async()=>{await synchronizeContract(contract.id);})} onSuppress={(occurrence)=>{setTargetOccurrence(occurrence);setModal("suppress");}} onResume={(occurrence)=>void runAction(`occurrence:${occurrence.source_key}`,async()=>{await resumeContractOccurrence(contract.id,occurrence.source_key);})} onResumeAndGenerate={(occurrence)=>void runAction(`occurrence:${occurrence.source_key}`,async()=>{await resumeAndGenerateContractOccurrence(contract.id,occurrence.source_key);})} onDeleteGenerated={(expense)=>{setTargetGeneratedExpense(expense);setAllowRegeneration(false);setModal("delete-expense");}} /></ComponentCard>
    </div> : activeTab === "attachments" ? <AttachmentPanel parent={{ kind: "contract", contractId: contract.id }} /> : <RevisionHistoryPanel revisions={history} loading={historyLoading} loadError={historyError} onReload={loadHistory} onCompare={(revisionId) => getContractRevision(contract.id, revisionId)} onRestore={async (revisionId) => { setContract(await restoreContractRevision(contract.id, revisionId, contract.lock_version)); await loadHistory(); }} />}
  </div>;

  return <><PageMeta title="Dettaglio Contratto | Master Plan IT" description="Dettaglio del contratto" /><PageBreadcrumb pageTitle="Dettaglio Contratto" />{body}<Modal isOpen={modal!==null} onClose={closeModal} className="max-w-lg p-6">{modal ? <ModalContent kind={modal} reason={reason} setReason={setReason} generationYear={generationYear} setGenerationYear={setGenerationYear} allowRegeneration={allowRegeneration} setAllowRegeneration={setAllowRegeneration} busy={busyKey!==null} onCancel={closeModal} onConfirm={()=>void confirmModal()} /> : null}</Modal></>;
}

function ModalContent({kind,reason,setReason,generationYear,setGenerationYear,allowRegeneration,setAllowRegeneration,busy,onCancel,onConfirm}:{kind:Exclude<ModalKind,null>;reason:string;setReason:(value:string)=>void;generationYear:string;setGenerationYear:(value:string)=>void;allowRegeneration:boolean;setAllowRegeneration:(value:boolean)=>void;busy:boolean;onCancel:()=>void;onConfirm:()=>void}) {
  const content={"delete-contract":["Eliminare il contratto?","Elimina Contratto"],generate:["Genera Ricorrenza","Genera"],suppress:["Sopprimere la ricorrenza?","Sopprimi"],"delete-term":["Rimuovere il termine?","Rimuovi Termine"],"delete-expense":["Eliminare la spesa generata?","Elimina Spesa"]} as const;
  return <><h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">{content[kind][0]}</h2>{kind==="generate" ? <div className="mt-5"><Label htmlFor="generation-year">Anno di pianificazione</Label><InputField id="generation-year" type="number" min="1000" max="9999" value={generationYear} onChange={(event)=>setGenerationYear(event.target.value)} /></div> : null}{kind==="delete-expense" ? <div className="mt-5"><Checkbox label="Consenti una nuova generazione" checked={allowRegeneration} onChange={setAllowRegeneration} /></div> : null}{["delete-contract","suppress","delete-term"].includes(kind) ? <div className="mt-5"><Label htmlFor="contract-action-reason">Motivo (opzionale)</Label><InputField id="contract-action-reason" value={reason} onChange={(event)=>setReason(event.target.value)} /></div> : null}<div className="mt-6 flex justify-end gap-3"><Button variant="outline" onClick={onCancel} disabled={busy}>Annulla</Button><Button onClick={onConfirm} disabled={busy}>{busy ? "Operazione in corso…" : content[kind][1]}</Button></div></>;
}
