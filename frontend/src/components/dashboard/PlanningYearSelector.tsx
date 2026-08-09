import Label from "../form/Label";
import Select from "../form/Select";
import { usePlanningYear } from "../../context/PlanningYearContext";

export default function PlanningYearSelector() {
  const { activePlanningYears, selectedPlanningYearId, selectPlanningYear } = usePlanningYear();

  if (activePlanningYears.length === 0) return null;

  return (
    <div className="w-full min-w-52 sm:w-auto">
      <Label htmlFor="selected-planning-year">Anno di pianificazione</Label>
      <Select
        key={selectedPlanningYearId ?? "none"}
        options={activePlanningYears.map((planningYear) => ({
          value: String(planningYear.id),
          label: String(planningYear.year_label),
        }))}
        id="selected-planning-year"
        value={selectedPlanningYearId === null ? "" : String(selectedPlanningYearId)}
        placeholder="Seleziona l'anno"
        onChange={(value) => selectPlanningYear(Number(value))}
      />
    </div>
  );
}
