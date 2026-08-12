import PlanningYearDropdown from "./PlanningYearDropdown";
import TenantDropdown from "./TenantDropdown";

/** The single visible Tenant/annual context control shared by all Slice 023 surfaces. */
export default function WorkspaceContextBar() {
  return (
    <section aria-label="Contesto di lavoro" className="flex items-center gap-2">
      <TenantDropdown />
      <PlanningYearDropdown />
    </section>
  );
}
