import { useCallback, useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router";
import { ApiError } from "../../api/client";
import { deleteContract, listContracts, type Contract } from "../../api/contracts";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ContractTable from "../../components/contracts/ContractTable";
import Alert from "../../components/ui/alert/Alert";
import Button from "../../components/ui/button/Button";
import { Modal } from "../../components/ui/modal";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";

export default function Contracts() {
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  const [contracts, setContracts] = useState<Contract[]>([]);
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Contract | null>(null);
  const [deleting, setDeleting] = useState(false);
  const tenantId = data?.tenant?.id ?? null;
  const canView = hasAbility("contract.view");
  const page = Number.parseInt(searchParams.get("page") ?? "1", 10) || 1;

  const load = useCallback(async () => {
    if (tenantId === null || !canView) return;
    setLoading(true); setError(null);
    try { const response = await listContracts({ page, per_page: 15 }); setContracts(response.data); setMeta({ current_page: response.meta.current_page, last_page: response.meta.last_page, total: response.meta.total }); }
    catch (requestError) { setContracts([]); setMeta(null); setError(ApiError.from(requestError)); }
    finally { setLoading(false); }
  }, [canView, page, tenantId]);

  useEffect(() => { if (!contextLoading) void load(); }, [contextLoading, load]);
  const confirmDelete = async () => { if (!deleteTarget) return; setDeleting(true); try { await deleteContract(deleteTarget.id, { lock_version: deleteTarget.lock_version }); setDeleteTarget(null); await load(); } catch (requestError) { setError(ApiError.from(requestError)); } finally { setDeleting(false); } };

  let body: React.ReactNode;
  if (contextLoading) body = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant in corso." />;
  else if (tenantId === null) body = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione prima di aprire i contratti." />;
  else if (!canView) body = <Alert variant="warning" title="Contratti non disponibili" message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina." />;
  else if (error) body = <Alert variant="error" title="Caricamento non riuscito" message={error.correlationId ? `${error.message} Riferimento tecnico: ${error.correlationId}` : error.message} />;
  else if (loading && contracts.length === 0) body = <Alert variant="info" title="Caricamento dei contratti" message="Recupero del registro in corso." />;
  else if (contracts.length === 0) body = <Alert variant="info" title="Nessun contratto" message="Non sono presenti contratti nel Tenant corrente." />;
  else body = <ContractTable contracts={contracts} canEdit={hasAbility("contract.update")} canDelete={hasAbility("contract.delete")} onDelete={setDeleteTarget} />;

  return <>
    <PageMeta title="Contratti | Master Plan IT" description="Registro dei contratti del Tenant" />
    <PageBreadcrumb pageTitle="Contratti" subtitle="Contratti, rinnovi e generazioni del Tenant corrente." actions={hasAbility("contract.create") && tenantId !== null ? <Button size="sm" onClick={() => navigate(routes.nuovoContratto)}>Nuovo Contratto</Button> : null} />
    <ComponentCard title="Registro Contratti">{body}{meta && meta.last_page > 1 ? <nav className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800" aria-label="Paginazione contratti"><Button size="sm" variant="outline" onClick={() => setSearchParams(page > 2 ? { page: String(page - 1) } : {})} disabled={page <= 1}>Precedente</Button><span className="text-sm text-gray-500 dark:text-gray-400">Pagina {meta.current_page} di {meta.last_page} · {meta.total} contratti</span><Button size="sm" variant="outline" onClick={() => setSearchParams({ page: String(page + 1) })} disabled={page >= meta.last_page}>Successiva</Button></nav> : null}</ComponentCard>
    <Modal isOpen={deleteTarget !== null} onClose={() => setDeleteTarget(null)} className="max-w-lg p-6"><h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Eliminare il contratto?</h2><p className="mt-2 text-sm text-gray-500 dark:text-gray-400">L'operazione è definitiva. Le spese già generate restano soggette alle regole del contratto.</p><div className="mt-6 flex justify-end gap-3"><Button variant="outline" onClick={() => setDeleteTarget(null)} disabled={deleting}>Annulla</Button><Button onClick={() => void confirmDelete()} disabled={deleting}>{deleting ? "Eliminazione…" : "Elimina Contratto"}</Button></div></Modal>
  </>;
}
