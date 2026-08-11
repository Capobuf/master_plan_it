import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate, useSearchParams } from "react-router";
import { ApiError, type PaginationMeta } from "../../api/client";
import {
  listExpenseContracts,
  listExpenseCostCenters,
  listExpenseVendors,
  listExpenses,
  type ExpenseContractOption,
  type ExpenseListParams,
  type ExpenseLookupOption,
  type ExpenseRegisterResponse,
} from "../../api/expenses";
import { listProjectOptions, type ProjectLookupOption } from "../../api/projects";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ExpenseFilters from "../../components/expenses/ExpenseFilters";
import ExpensePagination from "../../components/expenses/ExpensePagination";
import ExpenseRegisterTable from "../../components/expenses/ExpenseRegisterTable";
import ExpenseTotals from "../../components/expenses/ExpenseTotals";
import Alert from "../../components/ui/alert/Alert";
import Button from "../../components/ui/button/Button";
import { useApplicationContext } from "../../context/ApplicationContext";
import { usePlanningYear } from "../../context/PlanningYearContext";
import { routes } from "../../navigation/routes";

const defaultPerPage = 25;

function positive(searchParams: URLSearchParams, name: string): number | undefined {
  const raw = searchParams.get(name);
  return raw && /^\d+$/.test(raw) && Number(raw) > 0 ? Number(raw) : undefined;
}

function readParams(searchParams: URLSearchParams): ExpenseListParams {
  const state = searchParams.get("state");
  return {
    kind: searchParams.get("kind") || undefined,
    q: searchParams.get("q") || undefined,
    cost_center_id: positive(searchParams, "cost_center_id"),
    project_id: positive(searchParams, "project_id"),
    contract_id: positive(searchParams, "contract_id"),
    vendor_id: positive(searchParams, "vendor_id"),
    state: state === "open" || state === "closed" ? state : undefined,
    page: positive(searchParams, "page") ?? 1,
    per_page: [25, 50, 100].includes(positive(searchParams, "per_page") ?? 0)
      ? positive(searchParams, "per_page")
      : defaultPerPage,
  };
}

function toSearchParams(params: ExpenseListParams): URLSearchParams {
  const next = new URLSearchParams();
  if (params.kind) next.set("kind", params.kind);
  if (params.q) next.set("q", params.q);
  for (const key of ["cost_center_id", "project_id", "contract_id", "vendor_id"] as const) {
    if (params[key]) next.set(key, String(params[key]));
  }
  if (params.state) next.set("state", params.state);
  if (params.page && params.page > 1) next.set("page", String(params.page));
  if (params.per_page && params.per_page !== defaultPerPage) next.set("per_page", String(params.per_page));
  return next;
}

interface RegisterState { tenantId: number; response: ExpenseRegisterResponse | null; error: ApiError | null; }

export default function ExpenseRegister() {
  const { data: applicationContext, loading: contextLoading, hasAbility } = useApplicationContext();
  const { activePlanningYears, selectedPlanningYearId, loading: planningYearLoading, selectPlanningYear } = usePlanningYear();
  const [searchParams, setSearchParams] = useSearchParams();
  const [registerState, setRegisterState] = useState<RegisterState | null>(null);
  const [costCenters, setCostCenters] = useState<ExpenseLookupOption[]>([]);
  const [vendors, setVendors] = useState<ExpenseLookupOption[]>([]);
  const [contracts, setContracts] = useState<ExpenseContractOption[]>([]);
  const [projects, setProjects] = useState<ProjectLookupOption[]>([]);
  const [lookupError, setLookupError] = useState<ApiError | null>(null);
  const [loading, setLoading] = useState(false);
  const [refreshVersion, setRefreshVersion] = useState(0);
  const navigate = useNavigate();
  const tenantId = applicationContext?.tenant?.id ?? null;
  const canView = hasAbility("expense.view");
  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestParams = useMemo(() => ({ ...params, planning_year_id: selectedPlanningYearId ?? undefined }), [params, selectedPlanningYearId]);
  const requestKey = `${tenantId}:${selectedPlanningYearId}:${toSearchParams(params).toString()}:${refreshVersion}`;
  const updateParams = useCallback((next: ExpenseListParams) => setSearchParams(toSearchParams(next)), [setSearchParams]);

  useEffect(() => {
    if (searchParams.has("planning_year_id")) setSearchParams(toSearchParams(params), { replace: true });
  }, [params, searchParams, setSearchParams]);

  useEffect(() => {
    if (tenantId === null || !canView) return;
    let active = true;
    setLookupError(null);
    void Promise.all([
      hasAbility("cost-center.view") ? listExpenseCostCenters() : Promise.resolve([]),
      hasAbility("vendor.view") ? listExpenseVendors() : Promise.resolve([]),
      hasAbility("contract.view") ? listExpenseContracts() : Promise.resolve([]),
      hasAbility("project.view") ? listProjectOptions() : Promise.resolve([]),
    ]).then(([centers, vendorItems, contractItems, projectItems]) => {
      if (active) { setCostCenters(centers); setVendors(vendorItems); setContracts(contractItems); setProjects(projectItems); }
    }).catch((error: unknown) => { if (active) setLookupError(ApiError.from(error)); });
    return () => { active = false; };
  }, [canView, hasAbility, tenantId]);

  useEffect(() => {
    if (contextLoading || planningYearLoading || tenantId === null || selectedPlanningYearId === null || !canView) return;
    let active = true;
    setLoading(true);
    void listExpenses(requestParams)
      .then((response) => { if (active) setRegisterState({ tenantId, response, error: null }); })
      .catch((error: unknown) => { if (active) setRegisterState({ tenantId, response: null, error: ApiError.from(error) }); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [canView, contextLoading, planningYearLoading, requestKey, requestParams, selectedPlanningYearId, tenantId]);

  const currentState = registerState?.tenantId === tenantId ? registerState : null;
  const response = currentState?.response;
  let content;
  if (contextLoading || planningYearLoading) content = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant e dell'anno di pianificazione in corso." />;
  else if (tenantId === null) content = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione prima di aprire le spese." />;
  else if (!canView) content = <Alert variant="warning" title="Registro non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina." />;
  else if (selectedPlanningYearId === null) content = <Alert variant="warning" title="Anno richiesto" message="Seleziona un Planning Year dall'intestazione." />;
  else if (currentState?.error) content = <Alert variant="error" title="Richiesta registro non riuscita" message={currentState.error.message} />;
  else if (!response) content = <Alert variant="info" title="Caricamento del registro" message="Recupero delle spese correnti in corso." />;
  else if (response.data.length === 0) content = <Alert variant="info" title="Nessuna spesa" message="Non ci sono spese correnti per i filtri selezionati." />;
  else content = <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
    <ExpenseRegisterTable
      key={requestKey}
      expenses={response.data}
      planningYearId={selectedPlanningYearId}
      planningYears={activePlanningYears.map((year) => ({ id: year.id, label: year.year_label, active: year.active }))}
      columnPreferences={response.column_preferences}
      canEdit={hasAbility("expense.update")}
      canCreate={hasAbility("expense.create")}
      canDelete={hasAbility("expense.delete")}
      canViewProjects={hasAbility("project.view")}
      disabled={loading}
      onPlanningYearChange={selectPlanningYear}
      onChanged={() => setRefreshVersion((version) => version + 1)}
    />
    <ExpensePagination
      meta={response.meta as PaginationMeta}
      onPageChange={(page) => updateParams({ ...params, page })}
      onPerPageChange={(perPage) => updateParams({ ...params, per_page: perPage, page: 1 })}
      disabled={loading}
    />
  </div>;

  return <>
    <PageMeta title="Spese | Master Plan IT" description="Registro delle spese correnti del Tenant" />
    <PageBreadcrumb pageTitle="Spese" subtitle="Registro delle spese dell'anno selezionato." actions={hasAbility("expense.create") ? <Button size="sm" onClick={() => navigate(routes.nuovaSpesa)}>Nuova Spesa</Button> : null} />
    <div className="space-y-4">
      <ComponentCard title="Registro Spese" compact>
        <ExpenseFilters value={params} costCenters={costCenters} vendors={vendors} projects={projects} contracts={contracts} showCostCenters={hasAbility("cost-center.view")} showVendors={hasAbility("vendor.view")} showProjects={hasAbility("project.view")} showContracts={hasAbility("contract.view")} onChange={updateParams} disabled={loading || tenantId === null || !canView} />
        {lookupError ? <Alert variant="warning" title="Lookup non disponibili" message={lookupError.message} /> : null}
        {content}
      </ComponentCard>
      {response ? <ExpenseTotals totals={response.totals} title="Totali del registro" /> : null}
    </div>
  </>;
}
