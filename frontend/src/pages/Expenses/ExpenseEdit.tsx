import { useParams } from "react-router";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ExpenseEditor from "../../components/expenses/ExpenseEditor";
import Alert from "../../components/ui/alert/Alert";

export default function ExpenseEdit() {
  const { expenseId: expenseIdParam } = useParams<{ expenseId: string }>();
  const expenseId = expenseIdParam && /^\d+$/.test(expenseIdParam) ? Number(expenseIdParam) : null;

  return (
    <>
      <PageMeta title="Modifica spesa | Master Plan IT" description="Modifica una spesa esistente" />
      <PageBreadcrumb pageTitle="Modifica spesa" />
      {expenseId === null ? (
        <Alert variant="error" title="Identificativo non valido" message="L'identificativo della spesa non è valido." />
      ) : (
        <ExpenseEditor expenseId={expenseId} />
      )}
    </>
  );
}
