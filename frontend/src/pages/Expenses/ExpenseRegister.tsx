import { useCallback, useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router";
import {
  ApiError,
  type PaginationMeta,
} from "../../api/client";
import {
  listExpenses,
  type ExpenseListParams,
  type ExpenseRegisterResponse,
} from "../../api/expenses";
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
import { useNavigate } from "react-router";

const defaultPerPage = 15;

function readParams(searchParams: URLSearchParams): ExpenseListParams {
  const numberParam = (name: string): number | undefined => {
    const raw = searchParams.get(name);
    if (!raw || !/^\d+$/.test(raw)) {
      return undefined;
    }
    const value = Number(raw);
    return value > 0 ? value : undefined;
  };

  const kind = searchParams.get("kind") || undefined;

  return {
    planning_year_id: numberParam("planning_year_id"),
    kind,
    page: numberParam("page") ?? 1,
    per_page: numberParam("per_page") ?? defaultPerPage,
  };
}

function writeParams(
  params: ExpenseListParams,
  setSearchParams: (next: URLSearchParams) => void,
) {
  const next = new URLSearchParams();
  if (params.planning_year_id) next.set("planning_year_id", String(params.planning_year_id));
  if (params.kind) next.set("kind", params.kind);
  if (params.page && params.page > 1) next.set("page", String(params.page));
  if (params.per_page && params.per_page !== defaultPerPage) next.set("per_page", String(params.per_page));
  setSearchParams(next);
}

interface RegisterState {
  tenantId: number;
  response: ExpenseRegisterResponse | null;
  error: ApiError | null;
}

export default function ExpenseRegister() {
  const { data: applicationContext, loading: contextLoading, hasAbility } =
    useApplicationContext();
  const {
    planningYears,
    activePlanningYears,
    selectedPlanningYearId,
    loading: planningYearLoading,
    selectPlanningYear,
  } = usePlanningYear();
  const [searchParams, setSearchParams] = useSearchParams();
  const [registerState, setRegisterState] = useState<RegisterState | null>(null);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const tenantId = applicationContext?.tenant?.id ?? null;
  const canView = hasAbility("expense.view");
  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = searchParams.toString();
  const updateParams = useCallback(
    (next: ExpenseListParams) => writeParams(next, setSearchParams),
    [setSearchParams],
  );

  useEffect(() => {
    if (
      contextLoading ||
      planningYearLoading ||
      tenantId === null ||
      !canView
    ) {
      return;
    }

    const requestedYear = params.planning_year_id;
    const requestedYearIsActive = activePlanningYears.some(
      (planningYear) => planningYear.id === requestedYear,
    );

    if (requestedYearIsActive) {
      if (selectedPlanningYearId !== requestedYear) {
        selectPlanningYear(requestedYear as number);
      }
      return;
    }

    if (selectedPlanningYearId !== null && requestedYear !== selectedPlanningYearId) {
      updateParams({ ...params, planning_year_id: selectedPlanningYearId, page: 1 });
    }
  }, [
    activePlanningYears,
    canView,
    contextLoading,
    params,
    planningYearLoading,
    selectPlanningYear,
    selectedPlanningYearId,
    tenantId,
    updateParams,
  ]);

  useEffect(() => {
    const waitingForYearSync =
      !planningYearLoading &&
      activePlanningYears.length > 0 &&
      params.planning_year_id === undefined;

    if (
      contextLoading ||
      planningYearLoading ||
      waitingForYearSync ||
      tenantId === null ||
      !canView
    ) {
      return;
    }

    let active = true;
    setLoading(true);
    void listExpenses(params)
      .then((response) => {
        if (active) {
          setRegisterState({ tenantId, response, error: null });
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setRegisterState({ tenantId, response: null, error: ApiError.from(error) });
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [
    activePlanningYears.length,
    canView,
    contextLoading,
    params,
    planningYearLoading,
    requestKey,
    tenantId,
  ]);

  const currentState = registerState?.tenantId === tenantId ? registerState : null;
  const response = currentState?.response;
  const yearOptions =
    planningYears.length > 0
      ? planningYears.map((planningYear) => ({
          id: planningYear.id,
          label: planningYear.year_label,
          active: planningYear.active,
        }))
      : response?.year_options ?? [];

  let content;
  if (contextLoading) {
    content = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant e dell'anno di pianificazione in corso." />;
  } else if (tenantId === null) {
    content = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant dall'intestazione prima di aprire le spese." />;
  } else if (!canView) {
    content = <Alert variant="warning" title="Registro non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare questa pagina." />;
  } else if (currentState?.error) {
    content = (
      <div className="space-y-3">
        <Alert
          variant="error"
          title="Richiesta registro non riuscita"
          message={`${currentState.error.message}${currentState.error.correlationId ? ` Riferimento tecnico: ${currentState.error.correlationId}` : ""}`}
        />
        <Button variant="outline" onClick={() => updateParams(params)} disabled={loading}>
          Riprova
        </Button>
      </div>
    );
  } else if (!response) {
    content = <Alert variant="info" title="Caricamento del registro" message="Recupero delle spese correnti in corso." />;
  } else if (response.data.length === 0) {
    content = <Alert variant="info" title="Nessuna spesa" message="Non ci sono spese correnti per i filtri selezionati." />;
  } else {
    content = (
      <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
        <ExpenseRegisterTable
          expenses={response.data}
          canEdit={hasAbility("expense.update")}
          canDelete={hasAbility("expense.delete")}
          canViewProjects={hasAbility("project.view")}
          disabled={loading}
          onDeleted={() => updateParams(params)}
        />
        <ExpensePagination
          meta={response.meta as PaginationMeta}
          onPageChange={(page) => updateParams({ ...params, page })}
          disabled={loading}
        />
      </div>
    );
  }

  return (
    <>
      <PageMeta title="Spese | Master Plan IT" description="Registro delle spese correnti del tenant" />
      <PageBreadcrumb pageTitle="Spese" subtitle="Registro delle spese dell'anno selezionato." actions={hasAbility("expense.create") ? <Button size="sm" onClick={() => navigate(routes.nuovaSpesa)}>Nuova Spesa</Button> : null} />
      <div className="space-y-6">
        <ComponentCard title="Registro Spese">
          <ExpenseFilters
            value={params}
            yearOptions={yearOptions}
            onChange={updateParams}
            disabled={loading || contextLoading || tenantId === null || !canView}
          />
          {content}
        </ComponentCard>
        {response?.totals && <ExpenseTotals totals={response.totals} title="Totali del registro" />}
      </div>
    </>
  );
}
