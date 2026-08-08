import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import {
  getApplicationContext,
  type ApplicationContextData,
} from "../api/context";
import { useAuth } from "./AuthContext";

interface ApplicationContextValue {
  data: ApplicationContextData | null;
  loading: boolean;
  refreshContext: () => Promise<ApplicationContextData | null>;
  hasAbility: (ability: string) => boolean;
}

const ApplicationContext = createContext<ApplicationContextValue | undefined>(
  undefined,
);

export function ApplicationProvider({ children }: { children: ReactNode }) {
  const { authenticated } = useAuth();
  const [data, setData] = useState<ApplicationContextData | null>(null);
  const [loading, setLoading] = useState(false);

  const refreshContext = useCallback(async () => {
    if (!authenticated) {
      setData(null);
      setLoading(false);
      return null;
    }

    setLoading(true);

    try {
      const applicationContext = await getApplicationContext();
      setData(applicationContext);
      return applicationContext;
    } finally {
      setLoading(false);
    }
  }, [authenticated]);

  useEffect(() => {
    let active = true;

    if (!authenticated) {
      setData(null);
      setLoading(false);
      return () => {
        active = false;
      };
    }

    setLoading(true);
    void getApplicationContext()
      .then((applicationContext) => {
        if (active) {
          setData(applicationContext);
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setData(null);
          console.error("Unable to load the application context.", error);
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
  }, [authenticated]);

  const hasAbility = useCallback(
    (ability: string) => data?.abilities.includes(ability) ?? false,
    [data?.abilities],
  );

  const value = useMemo<ApplicationContextValue>(
    () => ({ data, loading, refreshContext, hasAbility }),
    [data, loading, refreshContext, hasAbility],
  );

  return (
    <ApplicationContext.Provider value={value}>
      {children}
    </ApplicationContext.Provider>
  );
}

// The provider and its hook intentionally live together as one context module.
// eslint-disable-next-line react-refresh/only-export-components
export function useApplicationContext(): ApplicationContextValue {
  const context = useContext(ApplicationContext);

  if (context === undefined) {
    throw new Error(
      "useApplicationContext must be used within an ApplicationProvider",
    );
  }

  return context;
}
