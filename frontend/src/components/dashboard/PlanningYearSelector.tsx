import Label from "../form/Label";
import Select from "../form/Select";
import { usePlanningYear } from "../../context/PlanningYearContext";

export default function PlanningYearSelector() {
  const { activePlanningYears, selectedPlanningYearId, selectPlanningYear } = usePlanningYear();

  if (activePlanningYears.length === 0) return null;

  return (
    <div className="mb-6 max-w-xs">
      <Label htmlFor="selected-planning-year">Planning year</Label>
      <Select
        options={activePlanningYears.map((planningYear) => ({
          value: String(planningYear.id),
          label: String(planningYear.year_label),
        }))}
        defaultValue={selectedPlanningYearId === null ? "" : String(selectedPlanningYearId)}
        onChange={(value) => selectPlanningYear(Number(value))}
      />
    </div>
  );
}
