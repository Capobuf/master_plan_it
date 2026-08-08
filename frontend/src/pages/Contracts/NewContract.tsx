import { useState } from "react";
import { useNavigate } from "react-router";

import { ApiError } from "../../api/client";
import { createContract, type ContractWrite } from "../../api/contracts";
import ComponentCard from "../../components/common/ComponentCard";
import PageBreadcrumb from "../../components/common/PageBreadCrumb";
import PageMeta from "../../components/common/PageMeta";
import ContractForm from "../../components/contracts/ContractForm";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";

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
      navigate(`/contracts/${contract.id}`, { replace: true });
    } catch (requestError) {
      setError(ApiError.from(requestError));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageMeta title="New contract | Master Plan IT" description="Create a tenant contract" />
      <PageBreadcrumb pageTitle="New contract" />
      {contextLoading ? <Alert variant="info" title="Loading context" message="Checking tenant and contract ability." /> : tenantId === null ? <Alert variant="warning" title="Tenant required" message="Enter a tenant before creating a contract." /> : !canCreate ? <Alert variant="warning" title="Creation unavailable" message="The current context does not grant contract.create." /> : <ComponentCard title="New contract" desc="All fields are submitted to the documented contract write API."><ContractForm canSubmit={canCreate} submitting={submitting} error={error?.message ?? null} onSubmit={async (input) => submit(input as ContractWrite)} /></ComponentCard>}
    </>
  );
}
