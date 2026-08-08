import { useCallback, useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router";

import { ApiError } from "../../api/client";
import { deleteContract, listContracts, type Contract } from "../../api/contracts";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ContractTable from "../../components/contracts/ContractTable";
import InputField from "../../components/form/input/InputField";
import Label from "../../components/form/Label";
import Alert from "../../components/ui/alert/Alert";
import Button from "../../components/ui/button/Button";
import { Modal } from "../../components/ui/modal";
import { useApplicationContext } from "../../context/ApplicationContext";

export default function Contracts() {
  const { data: applicationContext, loading: contextLoading, hasAbility } = useApplicationContext();
  const [searchParams, setSearchParams] = useSearchParams();
  const [contracts, setContracts] = useState<Contract[]>([]);
  const [meta, setMeta] = useState<{ current_page: number; last_page: number; total: number } | null>(null);
  const [query, setQuery] = useState(searchParams.get("q") ?? "");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Contract | null>(null);
  const [actionError, setActionError] = useState<ApiError | null>(null);
  const [deleting, setDeleting] = useState(false);
  const tenantId = applicationContext?.tenant?.id ?? null;
  const canView = hasAbility("contract.view");
  const canCreate = hasAbility("contract.create");

  const load = useCallback(async (page = Number(searchParams.get("page") ?? "1")) => {
    if (tenantId === null || !canView) return;
    setLoading(true);
    setError(null);
    try {
      const response = await listContracts({ page: Number.isInteger(page) && page > 0 ? page : 1, per_page: 15, q: query || undefined });
      setContracts(response.data);
      setMeta({ current_page: response.meta.current_page, last_page: response.meta.last_page, total: response.meta.total });
    } catch (requestError) {
      setContracts([]);
      setMeta(null);
      setError(ApiError.from(requestError));
    } finally {
      setLoading(false);
    }
  }, [canView, query, searchParams, tenantId]);

  useEffect(() => { if (!contextLoading) void load(); }, [contextLoading, load]);

  const submitSearch = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const next = new URLSearchParams();
    if (query) next.set("q", query);
    setSearchParams(next);
  };

  const changePage = (page: number) => {
    const next = new URLSearchParams(searchParams);
    next.set("page", String(page));
    setSearchParams(next);
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    setActionError(null);
    try {
      await deleteContract(deleteTarget.id, { lock_version: deleteTarget.lock_version });
      setDeleteTarget(null);
      await load();
    } catch (requestError) {
      setActionError(ApiError.from(requestError));
    } finally {
      setDeleting(false);
    }
  };

  let body: React.ReactNode;
  if (contextLoading) body = <Alert variant="info" title="Loading context" message="Checking tenant and contract ability." />;
  else if (tenantId === null) body = <Alert variant="warning" title="Tenant required" message="Enter a tenant from the header before loading contracts." />;
  else if (!canView) body = <Alert variant="warning" title="Contracts unavailable" message="The current context does not grant contract.view." />;
  else if (error) body = <Alert variant="error" title="Contract request failed" message={error.correlationId ? `${error.message} Correlation ID: ${error.correlationId}` : error.message} />;
  else if (loading && contracts.length === 0) body = <Alert variant="info" title="Loading contracts" message="Requesting the tenant contract register." />;
  else if (contracts.length === 0) body = <p className="py-6 text-sm text-gray-500 dark:text-gray-400">No contracts match this search.</p>;
  else body = <ContractTable contracts={contracts} canEdit={hasAbility("contract.update")} canDelete={hasAbility("contract.delete")} onDelete={setDeleteTarget} />;

  return (
    <>
      <PageMeta title="Contracts | Master Plan IT" description="Tenant contract register" />
      <PageBreadcrumb pageTitle="Contracts" />
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-title-md font-semibold text-gray-800 dark:text-white/90">Contracts</h1><p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Tenant-scoped contract register and generation controls.</p></div>{canCreate && tenantId !== null ? <Link to="/contracts/new" className="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">New contract</Link> : null}</div>
        <ComponentCard title="Contract register">
          <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={submitSearch}><div className="flex-1"><Label htmlFor="contract-search">Search</Label><InputField id="contract-search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Title or vendor" /></div><Button>Search</Button></form>
          {actionError ? <Alert variant="error" title="Delete failed" message={actionError.message} /> : null}
          {body}
          {meta && meta.last_page > 1 ? <div className="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800"><Button size="sm" variant="outline" onClick={() => changePage(meta.current_page - 1)} disabled={meta.current_page <= 1}>Previous</Button><span className="text-sm text-gray-500">Page {meta.current_page} of {meta.last_page} · {meta.total} contracts</span><Button size="sm" variant="outline" onClick={() => changePage(meta.current_page + 1)} disabled={meta.current_page >= meta.last_page}>Next</Button></div> : null}
        </ComponentCard>
      </div>
      <Modal isOpen={deleteTarget !== null} onClose={() => setDeleteTarget(null)} className="max-w-lg p-6"><h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Delete contract?</h2><p className="mt-2 text-sm text-gray-500 dark:text-gray-400">This terminal operation keeps linked generated expenses according to the contract rules.</p><div className="mt-6 flex justify-end gap-3"><Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancel</Button><Button onClick={() => void confirmDelete()} disabled={deleting}>{deleting ? "Deleting…" : "Confirm delete"}</Button></div></Modal>
    </>
  );
}
