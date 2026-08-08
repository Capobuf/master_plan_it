import { BrowserRouter as Router, Routes, Route } from "react-router";
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
import Vendors from "./pages/Vendors/Home";
import CostCenters from "./pages/CostCenters/Home";
import PlanningYears from "./pages/PlanningYears/Home";
import Users from "./pages/Users/Home";
import Roles from "./pages/Roles/Home";
import Tenants from "./pages/Tenants/Home";
import ProtectedRoute from "./components/auth/ProtectedRoute";
import { AuthProvider } from "./context/AuthContext";
import { ApplicationProvider } from "./context/ApplicationContext";

export default function App() {
  return (
    <Router>
      <ScrollToTop />
      <AuthProvider>
        <ApplicationProvider>
          <Routes>
            <Route path="/signin" element={<SignIn />} />

            <Route element={<ProtectedRoute />}>
              <Route element={<AppLayout />}>
                <Route index path="/" element={<Dashboard />} />
                <Route path="/budget" element={<Budget />} />
                <Route path="/reports" element={<Reports />} />
                <Route path="/expenses" element={<ExpenseRegister />} />
                <Route path="/expenses/new" element={<ExpenseNew />} />
                <Route path="/expenses/:expenseId" element={<ExpenseDetail />} />
                <Route path="/expenses/:expenseId/edit" element={<ExpenseEdit />} />
                <Route path="/contracts" element={<Contracts />} />
                <Route path="/contracts/new" element={<NewContract />} />
                <Route path="/contracts/:contractId" element={<ContractDetail />} />
                <Route path="/contracts/:contractId/edit" element={<EditContract />} />
                <Route path="/vendors" element={<Vendors />} />
                <Route path="/cost-centers" element={<CostCenters />} />
                <Route path="/planning-years" element={<PlanningYears />} />
                <Route path="/users" element={<Users />} />
                <Route path="/roles" element={<Roles />} />
                <Route path="/tenants" element={<Tenants />} />
              </Route>
            </Route>

            <Route path="*" element={<NotFound />} />
          </Routes>
        </ApplicationProvider>
      </AuthProvider>
    </Router>
  );
}
