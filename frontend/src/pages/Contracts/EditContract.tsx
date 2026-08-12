import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";

import { ApiError } from "../../api/client";
import { getContract, updateContract, type Contract, type ContractUpdate } from "../../api/contracts";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ContractForm from "../../components/contracts/ContractForm";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { routes } from "../../navigation/routes";

export default function EditContract() {
  const { data, loading: contextLoading, hasAbility } = useApplicationContext();
  const { contractId: contractIdParam } = useParams<{ contractId: string }>();
  const navigate = useNavigate();
  const [contract, setContract] = useState<Contract | null>(null);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const tenantId = data?.tenant?.id ?? null;
  const contractId = contractIdParam && /^\d+$/.test(contractIdParam) ? Number(contractIdParam) : null;
  const canView = hasAbility("contract.view");
  const canEdit = hasAbility("contract.update");

  useEffect(() => {
    if (contextLoading || tenantId === null || contractId === null || !canView) return;
    let active = true;
    setLoading(true);
    void getContract(contractId).then((next) => { if (active) setContract(next); }).catch((requestError: unknown) => { if (active) setError(ApiError.from(requestError)); }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [canView, contextLoading, contractId, tenantId]);

  const submit = async (input: ContractUpdate) => {
    if (contractId === null) return;
    setSubmitting(true);
    setError(null);
    try {
      const updated = await updateContract(contractId, input);
      navigate(routes.contratto(updated.id), { replace: true });
    } catch (requestError) {
      setError(ApiError.from(requestError));
    } finally {
      setSubmitting(false);
    }
  };

  let body: React.ReactNode;
  if (contextLoading) body = <Alert variant="info" title="Caricamento del contesto" message="Verifica del Tenant in corso." />;
  else if (tenantId === null) body = <Alert variant="warning" title="Tenant richiesto" message="Seleziona un Tenant prima di modificare un contratto." />;
  else if (contractId === null) body = <Alert variant="error" title="Contratto non valido" message="Il contratto richiesto non è valido." />;
  else if (!canView) body = <Alert variant="warning" title="Contratto non disponibile" message="Non disponi dell'autorizzazione necessaria per visualizzare il contratto." />;
  else if (loading) body = <Alert variant="info" title="Caricamento del contratto" message="Recupero dei dati in corso." />;
  else if (error && contract === null) body = <Alert variant="error" title="Caricamento non riuscito" message={error.message} />;
  else if (contract === null) body = <Alert variant="error" title="Contratto non trovato" message="Non è stato possibile caricare il contratto." />;
  else if (!canEdit) body = <Alert variant="warning" title="Modifica non disponibile" message="Non disponi dell'autorizzazione necessaria per questa operazione." />;
  else body = <ContractForm contract={contract} defaultVatRate={data?.tenant?.default_vat_rate} canSubmit={canEdit} submitting={submitting} error={error} onSubmit={async (input) => submit(input as ContractUpdate)} />;

  return <><PageMeta title="Modifica Contratto | Master Plan IT" description="Modifica un contratto" /><PageBreadcrumb pageTitle="Modifica Contratto" subtitle="Aggiorna dati e termini mantenendo il controllo di versione corrente." />{body}</>;
}
