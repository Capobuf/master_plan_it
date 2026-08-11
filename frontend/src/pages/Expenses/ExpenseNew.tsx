import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ExpenseEditor from "../../components/expenses/ExpenseEditor";

export default function ExpenseNew() {
  return (
    <>
      <PageMeta title="Nuova Spesa | Master Plan IT" description="Crea una nuova spesa" />
      <PageBreadcrumb pageTitle="Nuova Spesa" subtitle="Inserisci i dati generali e almeno una riga di spesa." />
      <ExpenseEditor />
    </>
  );
}
