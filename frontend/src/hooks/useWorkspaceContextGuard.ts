import { useEffect } from "react";

import { usePlanningYear } from "../context/PlanningYearContext";

/** Registers an editor as a dirty workspace participant and releases it on unmount. */
export function useWorkspaceContextGuard(source: string, dirty: boolean) {
  const { registerDirtySource } = usePlanningYear();

  useEffect(() => {
    registerDirtySource(source, dirty);
    return () => registerDirtySource(source, false);
  }, [dirty, registerDirtySource, source]);
}
