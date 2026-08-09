import { Navigate, Outlet, useLocation } from "react-router";
import { useAuth } from "../../context/AuthContext";
import { routes } from "../../navigation/routes";

export default function ProtectedRoute() {
  const { authenticated, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return (
      <div
        className="flex min-h-screen items-center justify-center bg-white dark:bg-gray-900"
        role="status"
        aria-live="polite"
      >
        <p className="text-sm text-gray-500 dark:text-gray-400">
          Caricamento della sessione…
        </p>
      </div>
    );
  }

  if (!authenticated) {
    return <Navigate to={routes.accesso} replace state={{ from: location }} />;
  }

  return <Outlet />;
}
