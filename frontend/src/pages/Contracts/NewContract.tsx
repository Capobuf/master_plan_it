import { useState } from "react";
import { useNavigate } from "react-router";

import { ApiError } from "../../api/client";
import { createContract, type ContractWrite } from "../../api/contracts";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ContractForm from "../../components/contracts/ContractForm";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";

export default function NewContract() {
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const navigate = useNavigate();
  const [error, setError] = useState<ApiError | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const tenantId = data?.tenant?.id ?? null;
  const canCreate = hasAbility("contract.create");

  const submit = async (input: ContractWrite) => {
    setSubmitting(true);
    setError(null);
    try {
      const contract = await createContract(input);
      navigate(routes.contratto(contract.id), { replace: true });
    } catch (requestError) {
      setError(ApiError.from(requestError));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageMeta title="Nuovo Contratto | Master Plan IT" description="Crea un nuovo contratto" />
      <PageBreadcrumb pageTitle="Nuovo Contratto" subtitle="Inserisci i dati generali e i termini contrattuali." />
      {contextLoading ? <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant in corso." /> : tenantId === null ? <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant prima di creare un contratto." /> : !canCreate ? <Alert variant="warning" title="Creazione non disponibile" message="Non disponi dell'autorizzazione necessaria per questa operazione." /> : <ContractForm canSubmit={canCreate} submitting={submitting} error={error} onSubmit={async (input) => submit(input as ContractWrite)} />}
    </>
  );
}
