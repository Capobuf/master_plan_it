import { useEffect, useMemo, useState } from "react";
import { ApiError } from "../../api/client";
import { listExpenseCostCenters, listExpensePlanningYears, type ExpenseLookupOption, type ExpenseYearOption } from "../../api/expenses";
import type { Project, ProjectStage, ProjectUpdate, ProjectWrite } from "../../api/projects";
import { useInvalidFieldFocus, type InvalidFieldFocusRequest } from "../../hooks/useInvalidFieldFocus";
import { domainLabel } from "../../presentation/labels";
import ComponentCard from "../common/ComponentCard";
import Label from "../form/Label";
import InputField from "../form/input/InputField";
import Select from "../form/Select";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";

const NONE = "__none__";
const stages: ProjectStage[] = ["idea", "proposed", "approved", "deferred", "rejected"];
const projectFieldIds: Record<string, string> = {
  title: "project-title",
  cost_center_id: "project-cost-center",
  stage: "project-stage",
  deferred_target_planning_year_id: "project-target-year",
};

function projectValidationSuggestion(field: string): string {
  const suggestions: Record<string, string> = {
    title: "Inserisci un titolo valido.",
    cost_center_id: "Seleziona un Centro di costo disponibile.",
    stage: "Seleziona uno stage valido.",
    deferred_target_planning_year_id: "Seleziona un anno di destinazione disponibile per il Progetto rinviato.",
  };

  return suggestions[field] ?? "Controlla il valore inserito.";
}

export default function ProjectForm({ project, submitting, error, onSubmit, onCancel }: {
  project?: Project;
  submitting: boolean;
  error: ApiError | null;
  onSubmit: (input: ProjectWrite | ProjectUpdate) => Promise<void>;
  onCancel: () => void;
}) {
  const [title, setTitle] = useState(project?.title ?? "");
  const [costCenterId, setCostCenterId] = useState<number | null>(project?.cost_center_id ?? null);
  const [stage, setStage] = useState<ProjectStage>(project?.stage ?? "idea");
  const [targetYearId, setTargetYearId] = useState<number | null>(project?.deferred_target_planning_year_id ?? null);
  const [centers, setCenters] = useState<ExpenseLookupOption[]>([]);
  const [years, setYears] = useState<ExpenseYearOption[]>([]);
  const [lookupError, setLookupError] = useState<ApiError | null>(null);
  const [validation, setValidation] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
  const [focusRequest, setFocusRequest] = useState<InvalidFieldFocusRequest | null>(null);

  useInvalidFieldFocus(focusRequest);

  useEffect(() => {
    if (error?.handledStatus !== 422) return;
    const next = Object.fromEntries(Object.keys(error.fields).map((field) => [field, projectValidationSuggestion(field)]));
    setValidationErrors(next);
    const firstField = Object.keys(next)[0];
    if (firstField) setFocusRequest({ id: projectFieldIds[firstField] ?? "project-form" });
  }, [error]);

  useEffect(() => {
    let active = true;
    void Promise.all([listExpenseCostCenters(), listExpensePlanningYears()])
      .then(([nextCenters, nextYears]) => { if (active) { setCenters(nextCenters); setYears(nextYears); } })
      .catch((requestError: unknown) => { if (active) setLookupError(ApiError.from(requestError)); });
    return () => { active = false; };
  }, []);

  const centerOptions = useMemo(() => [
    ...(project?.cost_center && !centers.some(({ id }) => id === project.cost_center_id)
      ? [{ value: String(project.cost_center_id), label: project.cost_center.name }]
      : []),
    ...centers.map((center) => ({ value: String(center.id), label: center.name })),
  ], [centers, project]);
  const yearOptions = useMemo(() => [
    ...(project?.deferred_target_planning_year && !years.some(({ id }) => id === project.deferred_target_planning_year_id)
      ? [{ value: String(project.deferred_target_planning_year.id), label: String(project.deferred_target_planning_year.year_label) }]
      : []),
    ...years.filter((year) => year.active || year.id === project?.deferred_target_planning_year_id)
      .map((year) => ({ value: String(year.id), label: String(year.label) })),
  ], [project, years]);

  function clearValidationFields(fields: string[]) {
    setValidation(null);
    setValidationErrors((current) => {
      const next = { ...current };
      fields.forEach((field) => delete next[field]);
      return next;
    });
  }

  function showValidationErrors(next: Record<string, string>) {
    setValidationErrors(next);
    setValidation("Correggi il campo evidenziato.");
    const firstField = Object.keys(next)[0];
    if (firstField) setFocusRequest({ id: projectFieldIds[firstField] ?? "project-form" });
  }

  const fieldError = (field: string) => validationErrors[field];

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setValidation(null);
    setValidationErrors({});
    const selectedCostCenterId = costCenterId;
    const nextErrors: Record<string, string> = {};
    if (!title.trim()) nextErrors.title = "Inserisci un titolo.";
    if (selectedCostCenterId === null) nextErrors.cost_center_id = "Seleziona un Centro di costo.";
    if (stage === "deferred" && targetYearId === null) nextErrors.deferred_target_planning_year_id = "Seleziona un anno di destinazione.";
    if (!title.trim() || selectedCostCenterId === null || (stage === "deferred" && targetYearId === null)) {
      showValidationErrors(nextErrors);
      return;
    }
    const input: ProjectWrite = {
      title: title.trim(), cost_center_id: selectedCostCenterId, stage,
      deferred_target_planning_year_id: stage === "deferred" ? targetYearId : null,
    };
    await onSubmit(project ? { ...input, lock_version: project.lock_version } : input);
  };

  return <form id="project-form" tabIndex={-1} className="space-y-6 focus:outline-hidden" onSubmit={(event) => void submit(event)}>
    {error ? <Alert variant="error" title={error.handledStatus === 422 ? "Controlla i dati" : "Salvataggio non riuscito"} message={`${error.message}${error.handledStatus === 422 ? " Correggi il campo evidenziato." : ""}${error.correlationId ? ` Riferimento tecnico: ${error.correlationId}` : ""}`} /> : null}
    {lookupError ? <Alert variant="error" title="Dati di supporto non disponibili" message={lookupError.message} /> : null}
    {validation ? <Alert variant="warning" title="Controlla i campi" message={validation} /> : null}
    <ComponentCard title="Dati del Progetto">
      <div className="grid gap-4 md:grid-cols-2">
        <div className="md:col-span-2"><Label htmlFor="project-title">Titolo</Label><InputField id="project-title" value={title} onChange={(event) => { setTitle(event.target.value); clearValidationFields(["title"]); }} disabled={submitting} error={Boolean(fieldError("title"))} hint={fieldError("title")} /></div>
        <div><Label htmlFor="project-cost-center">Centro di costo</Label><Select id="project-cost-center" value={costCenterId === null ? NONE : String(costCenterId)} options={[{ value: NONE, label: "Seleziona un centro" }, ...centerOptions]} onChange={(value) => { setCostCenterId(value === NONE ? null : Number(value)); clearValidationFields(["cost_center_id"]); }} disabled={submitting} error={Boolean(fieldError("cost_center_id"))} hint={fieldError("cost_center_id")} /></div>
        <div><Label htmlFor="project-stage">Stage</Label><Select id="project-stage" value={stage} options={stages.map((value) => ({ value, label: domainLabel(value) }))} onChange={(value) => { const next = value as ProjectStage; setStage(next); clearValidationFields(["stage", "deferred_target_planning_year_id"]); if (next !== "deferred") setTargetYearId(null); }} disabled={submitting} error={Boolean(fieldError("stage"))} hint={fieldError("stage")} /></div>
        {stage === "deferred" ? <div className="md:col-span-2"><Label htmlFor="project-target-year">Anno di destinazione</Label><Select id="project-target-year" value={targetYearId === null ? NONE : String(targetYearId)} options={[{ value: NONE, label: "Seleziona un anno" }, ...yearOptions]} onChange={(value) => { setTargetYearId(value === NONE ? null : Number(value)); clearValidationFields(["deferred_target_planning_year_id"]); }} disabled={submitting} error={Boolean(fieldError("deferred_target_planning_year_id"))} hint={fieldError("deferred_target_planning_year_id")} /></div> : null}
      </div>
    </ComponentCard>
    <div className="flex justify-end gap-3"><Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>Annulla</Button><Button disabled={submitting || lookupError !== null}>{submitting ? "Salvataggio…" : project ? "Salva modifiche" : "Crea progetto"}</Button></div>
  </form>;
}
