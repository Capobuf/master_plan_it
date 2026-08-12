import { useCallback, useEffect } from "react";
import { Outlet, useBlocker, type BlockerFunction } from "react-router";

import { usePlanningYear } from "../context/PlanningYearContext";
import AppHeader from "./AppHeader";

const AppLayout: React.FC = () => {
  const {
    hasDirtySources,
    confirmDiscardChanges,
    consumeAuthorizedNavigation,
  } = usePlanningYear();
  const blocker = useBlocker(
    useCallback<BlockerFunction>(
      ({ currentLocation, nextLocation }) => {
        const destinationChanged =
          currentLocation.pathname !== nextLocation.pathname ||
          currentLocation.search !== nextLocation.search ||
          currentLocation.hash !== nextLocation.hash;

        if (destinationChanged && consumeAuthorizedNavigation()) return false;

        return (
          hasDirtySources &&
          destinationChanged &&
          !confirmDiscardChanges()
        );
      },
      [confirmDiscardChanges, consumeAuthorizedNavigation, hasDirtySources],
    ),
  );

  useEffect(() => {
    if (blocker.state === "blocked") blocker.reset();
  }, [blocker]);

  return (
    <div className="min-h-screen overflow-x-hidden">
      <AppHeader />
      <main className="w-full p-3 sm:p-4 md:p-6">
        <Outlet />
      </main>
    </div>
  );
};

export default AppLayout;
