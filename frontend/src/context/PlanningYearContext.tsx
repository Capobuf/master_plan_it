import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";

import { ApiError, apiClient, type PaginatedData } from "../api/client";

export interface PlanningYear {
  id: number;
  year_label: number;
  start_date: string;
  end_date: string;
  active: boolean;
  lock_version: number;
}

interface PlanningYearContextValue {
  planningYears: readonly PlanningYear[];
  activePlanningYears: readonly PlanningYear[];
  selectedPlanningYear: PlanningYear | null;
  selectedPlanningYearId: number | null;
  loading: boolean;
  error: ApiError | null;
  hasDirtySources: boolean;
  refreshPlanningYears: () => Promise<readonly PlanningYear[]>;
  selectPlanningYear: (
    planningYearId: number,
    authorizeFollowingNavigation?: boolean,
  ) => boolean;
  registerDirtySource: (source: string, dirty: boolean) => void;
  confirmDiscardChanges: () => boolean;
  consumeAuthorizedNavigation: () => boolean;
}

const PlanningYearContext = createContext<
  PlanningYearContextValue | undefined
>(undefined);

interface PlanningYearProviderProps {
  children: ReactNode;
  tenantId: number | null;
  canRead: boolean;
}

async function getAllPlanningYears(): Promise<PlanningYear[]> {
  const planningYears: PlanningYear[] = [];
  let page = 1;
  let lastPage = 1;

  do {
    const response = await apiClient.get<PaginatedData<PlanningYear>>(
      "/api/v1/planning-years",
      {
        params: { page, per_page: 100 },
      },
    );

    planningYears.push(...response.data.data);
    lastPage = response.data.meta.last_page;
    page = response.data.meta.current_page + 1;
  } while (page <= lastPage);

  return planningYears;
}

export function PlanningYearProvider({
  children,
  tenantId,
  canRead,
}: PlanningYearProviderProps) {
  const [planningYears, setPlanningYears] = useState<PlanningYear[]>([]);
  const [selectedPlanningYearId, setSelectedPlanningYearId] = useState<
    number | null
  >(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [dirtySources, setDirtySources] = useState<Set<string>>(new Set());
  const requestGeneration = useRef(0);
  const authorizedNavigation = useRef(false);

  const confirmDiscardChanges = useCallback(
    () =>
      dirtySources.size === 0 ||
      window.confirm("Ci sono modifiche non salvate. Vuoi abbandonarle?"),
    [dirtySources.size],
  );

  useEffect(() => {
    if (dirtySources.size === 0) return;

    const warnBeforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
    };
    window.addEventListener("beforeunload", warnBeforeUnload);

    return () => window.removeEventListener("beforeunload", warnBeforeUnload);
  }, [dirtySources.size]);

  const refreshPlanningYears = useCallback(async () => {
    const generation = ++requestGeneration.current;
    if (tenantId === null || !canRead) {
      setPlanningYears([]);
      setSelectedPlanningYearId(null);
      setError(null);
      setLoading(false);
      return [];
    }

    setLoading(true);
    setError(null);

    try {
      const years = await getAllPlanningYears();
      if (generation !== requestGeneration.current) return [];
      setPlanningYears(years);
      return years;
    } catch (requestError) {
      const apiError = ApiError.from(requestError);
      setPlanningYears([]);
      setError(apiError);
      throw apiError;
    } finally {
      if (generation === requestGeneration.current) setLoading(false);
    }
  }, [canRead, tenantId]);

  useEffect(() => {
    const generation = ++requestGeneration.current;
    let active = true;

    if (tenantId === null || !canRead) {
      setPlanningYears([]);
      setSelectedPlanningYearId(null);
      setError(null);
      setLoading(false);
      return () => {
        active = false;
      };
    }

    setLoading(true);
    setError(null);

    void getAllPlanningYears()
      .then((years) => {
        if (active && generation === requestGeneration.current) {
          setPlanningYears(years);
        }
      })
      .catch((requestError: unknown) => {
        if (active && generation === requestGeneration.current) {
          setPlanningYears([]);
          setError(ApiError.from(requestError));
        }
      })
      .finally(() => {
        if (active && generation === requestGeneration.current) {
          setLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [canRead, tenantId]);

  const activePlanningYears = useMemo(
    () => planningYears.filter((planningYear) => planningYear.active),
    [planningYears],
  );

  useEffect(() => {
    const selectedStillExists = activePlanningYears.some(
      (planningYear) => planningYear.id === selectedPlanningYearId,
    );

    if (!selectedStillExists) {
      setSelectedPlanningYearId(activePlanningYears[0]?.id ?? null);
    }
  }, [activePlanningYears, selectedPlanningYearId]);

  const selectedPlanningYear = useMemo(
    () =>
      activePlanningYears.find(
        (planningYear) => planningYear.id === selectedPlanningYearId,
      ) ?? null,
    [activePlanningYears, selectedPlanningYearId],
  );

  const selectPlanningYear = useCallback(
    (planningYearId: number, authorizeFollowingNavigation = false) => {
      if (!activePlanningYears.some((planningYear) => planningYear.id === planningYearId)) return false;
      if (planningYearId === selectedPlanningYearId) return true;
      if (!confirmDiscardChanges()) return false;

      authorizedNavigation.current = authorizeFollowingNavigation;
      setSelectedPlanningYearId(planningYearId);
      return true;
    },
    [activePlanningYears, confirmDiscardChanges, selectedPlanningYearId],
  );

  const registerDirtySource = useCallback((source: string, dirty: boolean) => {
    setDirtySources((current) => {
      const next = new Set(current);
      if (dirty) next.add(source); else next.delete(source);
      return next;
    });
  }, []);

  const consumeAuthorizedNavigation = useCallback(() => {
    if (!authorizedNavigation.current) return false;
    authorizedNavigation.current = false;
    return true;
  }, []);

  const value = useMemo<PlanningYearContextValue>(
    () => ({
      planningYears,
      activePlanningYears,
      selectedPlanningYear,
      selectedPlanningYearId,
      loading,
      error,
      hasDirtySources: dirtySources.size > 0,
      refreshPlanningYears,
      selectPlanningYear,
      registerDirtySource,
      confirmDiscardChanges,
      consumeAuthorizedNavigation,
    }),
    [
      activePlanningYears,
      error,
      confirmDiscardChanges,
      consumeAuthorizedNavigation,
      dirtySources.size,
      loading,
      planningYears,
      refreshPlanningYears,
      registerDirtySource,
      selectPlanningYear,
      selectedPlanningYear,
      selectedPlanningYearId,
    ],
  );

  return (
    <PlanningYearContext.Provider value={value}>
      {children}
    </PlanningYearContext.Provider>
  );
}

// The provider and its hook intentionally live together as one context module.
// eslint-disable-next-line react-refresh/only-export-components
export function usePlanningYear(): PlanningYearContextValue {
  const context = useContext(PlanningYearContext);

  if (context === undefined) {
    throw new Error("usePlanningYear must be used within a PlanningYearProvider");
  }

  return context;
}
