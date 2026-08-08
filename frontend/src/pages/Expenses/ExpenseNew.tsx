import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ExpenseEditor from "../../components/expenses/ExpenseEditor";

export default function ExpenseNew() {
  return (
    <>
      <PageMeta title="Nuova spesa | Master Plan IT" description="Crea una nuova spesa" />
      <PageBreadcrumb pageTitle="Nuova spesa" />
      <ExpenseEditor />
    </>
  );
}
