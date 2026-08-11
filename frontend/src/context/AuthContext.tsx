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
  getCurrentUser,
  login as authenticate,
  logout as endSession,
  type LoginCredentials,
} from "../api/auth";
import { ApiError, type User } from "../api/client";

interface AuthContextValue {
  currentUser: User | null;
  loading: boolean;
  authenticated: boolean;
  refreshUser: () => Promise<User | null>;
  login: (credentials: LoginCredentials) => Promise<User>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const refreshUser = useCallback(async () => {
    setLoading(true);

    try {
      const user = await getCurrentUser();
      setCurrentUser(user);
      return user;
    } catch (error) {
      const apiError = ApiError.from(error);

      if (apiError.status === 401) {
        setCurrentUser(null);
        return null;
      }

      throw apiError;
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;

    void getCurrentUser()
      .then((user) => {
        if (active) {
          setCurrentUser(user);
        }
      })
      .catch((error: unknown) => {
        const apiError = ApiError.from(error);

        if (active && apiError.status !== 401) {
          console.error("Impossibile ripristinare la sessione autenticata.", apiError);
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
  }, []);

  const login = useCallback(async (credentials: LoginCredentials) => {
    const user = await authenticate(credentials);
    setCurrentUser(user);
    return user;
  }, []);

  const logout = useCallback(async () => {
    try {
      await endSession();
    } catch (error) {
      const apiError = ApiError.from(error);

      if (apiError.status !== 401) {
        throw apiError;
      }
    }

    setCurrentUser(null);
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      currentUser,
      loading,
      authenticated: currentUser !== null,
      refreshUser,
      login,
      logout,
    }),
    [currentUser, loading, refreshUser, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// The provider and its hook intentionally live together as one context module.
// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }

  return context;
}
