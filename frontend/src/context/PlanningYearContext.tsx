import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
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
  refreshPlanningYears: () => Promise<readonly PlanningYear[]>;
  selectPlanningYear: (planningYearId: number) => void;
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

  const refreshPlanningYears = useCallback(async () => {
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
      setPlanningYears(years);
      return years;
    } catch (requestError) {
      const apiError = ApiError.from(requestError);
      setPlanningYears([]);
      setError(apiError);
      throw apiError;
    } finally {
      setLoading(false);
    }
  }, [canRead, tenantId]);

  useEffect(() => {
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
        if (active) {
          setPlanningYears(years);
        }
      })
      .catch((requestError: unknown) => {
        if (active) {
          setPlanningYears([]);
          setError(ApiError.from(requestError));
        }
      })
      .finally(() => {
        if (active) {
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
    (planningYearId: number) => {
      if (
        activePlanningYears.some(
          (planningYear) => planningYear.id === planningYearId,
        )
      ) {
        setSelectedPlanningYearId(planningYearId);
      }
    },
    [activePlanningYears],
  );

  const value = useMemo<PlanningYearContextValue>(
    () => ({
      planningYears,
      activePlanningYears,
      selectedPlanningYear,
      selectedPlanningYearId,
      loading,
      error,
      refreshPlanningYears,
      selectPlanningYear,
    }),
    [
      activePlanningYears,
      error,
      loading,
      planningYears,
      refreshPlanningYears,
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
