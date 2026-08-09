import { useState } from "react";

import { usePlanningYear } from "../../context/PlanningYearContext";
import { CalenderIcon, CheckCircleIcon, ChevronDownIcon } from "../../icons";
import Alert from "../ui/alert/Alert";
import { Dropdown } from "../ui/dropdown/Dropdown";
import { DropdownItem } from "../ui/dropdown/DropdownItem";

export default function PlanningYearDropdown() {
  const {
    activePlanningYears,
    selectedPlanningYear,
    selectPlanningYear,
    loading,
    error,
  } = usePlanningYear();
  const [isOpen, setIsOpen] = useState(false);

  const handleSelect = (planningYearId: number) => {
    selectPlanningYear(planningYearId);
    setIsOpen(false);
  };

  const buttonLabel = loading
    ? "Caricamento anno…"
    : selectedPlanningYear
      ? String(selectedPlanningYear.year_label)
      : "Nessun anno";

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((current) => !current)}
        className="dropdown-toggle flex h-11 items-center gap-2 rounded-lg border border-gray-200 px-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:text-gray-400"
        aria-label={`Anno di pianificazione: ${buttonLabel}. Apri il menu`}
        aria-expanded={isOpen}
        aria-controls="planning-year-dropdown"
      >
        <CalenderIcon className="h-5 w-5 fill-gray-500 dark:fill-gray-400" />
        <span>{buttonLabel}</span>
        <ChevronDownIcon
          className={`stroke-gray-500 transition-transform duration-200 dark:stroke-gray-400 ${
            isOpen ? "rotate-180" : ""
          }`}
        />
      </button>

      <Dropdown
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        triggerId="planning-year-dropdown"
        className="absolute right-0 mt-[17px] flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
      >
        <div className="border-b border-gray-200 pb-3 dark:border-gray-800">
          <span className="block text-theme-xs text-gray-500 dark:text-gray-400">
            Anno di pianificazione
          </span>
          <span className="mt-0.5 block font-medium text-gray-700 text-theme-sm dark:text-gray-400">
            {buttonLabel}
          </span>
        </div>

        {error ? (
          <div className="mt-3">
            <Alert
              variant="error"
              title="Anni non disponibili"
              message={error.correlationId ? `${error.message} Riferimento tecnico: ${error.correlationId}` : error.message}
            />
          </div>
        ) : null}

        <ul className="flex flex-col gap-1 pt-3">
          {loading ? (
            <li className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
              Caricamento degli anni…
            </li>
          ) : null}

          {!loading && !error && activePlanningYears.length === 0 ? (
            <li className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
              Nessun anno attivo disponibile.
            </li>
          ) : null}

          {activePlanningYears.map((planningYear) => {
            const selected = planningYear.id === selectedPlanningYear?.id;

            return (
              <li key={planningYear.id}>
                <DropdownItem
                  onClick={() => handleSelect(planningYear.id)}
                  baseClassName="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left font-medium text-gray-700 text-theme-sm hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                >
                  <span>{planningYear.year_label}</span>
                  {selected ? (
                    <CheckCircleIcon className="h-5 w-5 fill-brand-500" />
                  ) : null}
                </DropdownItem>
              </li>
            );
          })}
        </ul>
      </Dropdown>
    </div>
  );
}
