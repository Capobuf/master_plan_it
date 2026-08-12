import {
  createBrowserRouter,
  createRoutesFromElements,
  Navigate,
  Outlet,
  Route,
  RouterProvider,
  useParams,
} from "react-router";
import { useState } from "react";
import SignIn from "./pages/AuthPages/SignIn";
import NotFound from "./pages/OtherPage/NotFound";
import AppLayout from "./layout/AppLayout";
import { ScrollToTop } from "./components/common/ScrollToTop";
import Dashboard from "./pages/Dashboard/Home";
import Budget from "./pages/Budget/Home";
import Reports from "./pages/Reports/Home";
import ExpenseRegister from "./pages/Expenses/ExpenseRegister";
import ExpenseDetail from "./pages/Expenses/ExpenseDetail";
import ExpenseNew from "./pages/Expenses/ExpenseNew";
import ExpenseEdit from "./pages/Expenses/ExpenseEdit";
import Contracts from "./pages/Contracts/Contracts";
import ContractDetail from "./pages/Contracts/ContractDetail";
import NewContract from "./pages/Contracts/NewContract";
import EditContract from "./pages/Contracts/EditContract";
import Projects from "./pages/Projects/Projects";
import ProjectDetail from "./pages/Projects/ProjectDetail";
import NewProject from "./pages/Projects/NewProject";
import EditProject from "./pages/Projects/EditProject";
import Vendors from "./pages/Vendors/Home";
import CostCenters from "./pages/CostCenters/Home";
import PlanningYears from "./pages/PlanningYears/Home";
import Users from "./pages/Users/Home";
import Roles from "./pages/Roles/Home";
import Tenants from "./pages/Tenants/Home";
import TenantGeneralSettings from "./pages/Settings/General";
import SettingsIndex from "./pages/Settings/Index";
import SettingsLayout from "./pages/Settings/Layout";
import ProtectedRoute from "./components/auth/ProtectedRoute";
import { AuthProvider } from "./context/AuthContext";
import { ApplicationProvider } from "./context/ApplicationContext";
import { legacyRoutePatterns, legacyRoutes, routePatterns, routes } from "./navigation/routes";

function createApplicationRouter() {
  return createBrowserRouter(createRoutesFromElements(
    <Route element={<ApplicationRoot />}>
            <Route path={routes.accesso} element={<SignIn />} />
            <Route path={legacyRoutes.accesso} element={<Navigate to={routes.accesso} replace />} />

            <Route element={<ProtectedRoute />}>
              <Route element={<AppLayout />}>
                <Route index element={<Dashboard />} />
                <Route path={routes.budget} element={<Budget />} />
                <Route path={routes.report} element={<Reports />} />
                <Route path={routes.spese} element={<ExpenseRegister />} />
                <Route path={routes.nuovaSpesa} element={<ExpenseNew />} />
                <Route path={routePatterns.spesa} element={<ExpenseDetail />} />
                <Route path={routePatterns.modificaSpesa} element={<ExpenseEdit />} />
                <Route path={routes.contratti} element={<Contracts />} />
                <Route path={routes.nuovoContratto} element={<NewContract />} />
                <Route path={routePatterns.contratto} element={<ContractDetail />} />
                <Route path={routePatterns.modificaContratto} element={<EditContract />} />
                <Route path={routes.progetti} element={<Projects />} />
                <Route path={routes.nuovoProgetto} element={<NewProject />} />
                <Route path={routePatterns.progetto} element={<ProjectDetail />} />
                <Route path={routePatterns.modificaProgetto} element={<EditProject />} />
                <Route path={routes.fornitori} element={<Vendors />} />
                <Route path={routes.tenant} element={<Tenants />} />
                <Route path={routes.impostazioni} element={<SettingsLayout />}>
                  <Route index element={<SettingsIndex />} />
                  <Route path={routes.impostazioniGenerali} element={<TenantGeneralSettings />} />
                  <Route path={routes.impostazioniUtenti} element={<Users />} />
                  <Route path={routes.impostazioniRuoli} element={<Roles />} />
                  <Route path={routes.impostazioniAnni} element={<PlanningYears />} />
                  <Route path={routes.impostazioniCentri} element={<CostCenters />} />
                </Route>

                <Route path={legacyRoutes.report} element={<Navigate to={routes.report} replace />} />
                <Route path={legacyRoutePatterns.nuovaSpesa} element={<Navigate to={routes.nuovaSpesa} replace />} />
                <Route path={legacyRoutePatterns.modificaSpesa} element={<LegacyExpenseEditRedirect />} />
                <Route path={legacyRoutePatterns.spesa} element={<LegacyExpenseRedirect />} />
                <Route path={legacyRoutes.spese} element={<Navigate to={routes.spese} replace />} />
                <Route path={legacyRoutePatterns.nuovoContratto} element={<Navigate to={routes.nuovoContratto} replace />} />
                <Route path={legacyRoutePatterns.modificaContratto} element={<LegacyContractEditRedirect />} />
                <Route path={legacyRoutePatterns.contratto} element={<LegacyContractRedirect />} />
                <Route path={legacyRoutes.contratti} element={<Navigate to={routes.contratti} replace />} />
                <Route path={legacyRoutes.fornitori} element={<Navigate to={routes.fornitori} replace />} />
                <Route path={legacyRoutes.centriDiCosto} element={<Navigate to={routes.impostazioniCentri} replace />} />
                <Route path={legacyRoutes.anniDiPianificazione} element={<Navigate to={routes.impostazioniAnni} replace />} />
                <Route path={legacyRoutes.utenti} element={<Navigate to={routes.impostazioniUtenti} replace />} />
                <Route path={legacyRoutes.ruoli} element={<Navigate to={routes.impostazioniRuoli} replace />} />
                <Route path={legacyRoutes.tenant} element={<Navigate to={routes.tenant} replace />} />
                <Route path={routes.centriDiCosto} element={<Navigate to={routes.impostazioniCentri} replace />} />
                <Route path={routes.anniDiPianificazione} element={<Navigate to={routes.impostazioniAnni} replace />} />
                <Route path={routes.utenti} element={<Navigate to={routes.impostazioniUtenti} replace />} />
                <Route path={routes.ruoli} element={<Navigate to={routes.impostazioniRuoli} replace />} />
              </Route>
            </Route>

      <Route path="*" element={<NotFound />} />
    </Route>,
  ));
}

export default function App() {
  const [router] = useState(createApplicationRouter);

  return <RouterProvider router={router} />;
}

function ApplicationRoot() {
  return (
    <AuthProvider>
      <ApplicationProvider>
        <ScrollToTop />
        <Outlet />
      </ApplicationProvider>
    </AuthProvider>
  );
}

function LegacyExpenseRedirect() {
  const { expenseId = "" } = useParams();
  return <Navigate to={routes.spesa(expenseId)} replace />;
}

function LegacyExpenseEditRedirect() {
  const { expenseId = "" } = useParams();
  return <Navigate to={routes.modificaSpesa(expenseId)} replace />;
}

function LegacyContractRedirect() {
  const { contractId = "" } = useParams();
  return <Navigate to={routes.contratto(contractId)} replace />;
}

function LegacyContractEditRedirect() {
  const { contractId = "" } = useParams();
  return <Navigate to={routes.modificaContratto(contractId)} replace />;
}
