import { Navigate, Outlet, useLocation } from "react-router";
import { useAuth } from "../../context/AuthContext";

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
          Loading session…
        </p>
      </div>
    );
  }

  if (!authenticated) {
    return <Navigate to="/signin" replace state={{ from: location }} />;
  }

  return <Outlet />;
}
