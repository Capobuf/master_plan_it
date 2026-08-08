import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";

import { ApiError } from "../../api/client";
import { getContract, updateContract, type Contract, type ContractUpdate } from "../../api/contracts";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ContractForm from "../../components/contracts/ContractForm";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";

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
      navigate(`/contracts/${updated.id}`, { replace: true });
    } catch (requestError) {
      setError(ApiError.from(requestError));
    } finally {
      setSubmitting(false);
    }
  };

  let body: React.ReactNode;
  if (contextLoading) body = <Alert variant="info" title="Loading context" message="Checking tenant and contract ability." />;
  else if (tenantId === null) body = <Alert variant="warning" title="Tenant required" message="Enter a tenant before editing a contract." />;
  else if (contractId === null) body = <Alert variant="error" title="Invalid contract ID" message="The route contract ID is not valid." />;
  else if (!canView) body = <Alert variant="warning" title="Contract unavailable" message="The current context does not grant contract.view." />;
  else if (loading) body = <Alert variant="info" title="Loading contract" message="Requesting the contract detail." />;
  else if (error && contract === null) body = <Alert variant="error" title="Contract request failed" message={error.message} />;
  else if (contract === null) body = <Alert variant="error" title="Contract not found" message="The contract detail could not be loaded." />;
  else if (!canEdit) body = <Alert variant="warning" title="Editing unavailable" message="The current context does not grant contract.update." />;
  else body = <ContractForm contract={contract} canSubmit={canEdit} submitting={submitting} error={error?.message ?? null} onSubmit={async (input) => submit(input as ContractUpdate)} />;

  return <><PageMeta title="Edit contract | Master Plan IT" description="Edit a tenant contract" /><PageBreadcrumb pageTitle="Edit contract" /><ComponentCard title="Edit contract" desc="The server lock version protects concurrent edits.">{body}</ComponentCard></>;
}
