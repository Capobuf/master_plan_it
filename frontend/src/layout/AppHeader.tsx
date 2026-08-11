import { useState } from "react";
import { Link } from "react-router";

import { ThemeToggleButton } from "../components/common/ThemeToggleButton";
import PlanningYearDropdown from "../components/header/PlanningYearDropdown";
import TenantDropdown from "../components/header/TenantDropdown";
import UserDropdown from "../components/header/UserDropdown";
import { useSidebar } from "../context/SidebarContext";
import { CloseLineIcon, ListIcon, MoreDotIcon } from "../icons";
import { routes } from "../navigation/routes";

const AppHeader: React.FC = () => {
  const [isApplicationMenuOpen, setApplicationMenuOpen] = useState(false);
  const { isMobileOpen, toggleSidebar, toggleMobileSidebar } = useSidebar();

  const handleToggle = () => {
    if (window.innerWidth >= 1024) {
      toggleSidebar();
    } else {
      setApplicationMenuOpen(false);
      toggleMobileSidebar();
    }
  };

  return (
    <header className="sticky top-0 z-99999 flex w-full border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 lg:border-b">
      <div className="flex grow flex-col items-center justify-between lg:flex-row lg:px-6">
        <div className="flex w-full items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 dark:border-gray-800 sm:gap-4 lg:justify-normal lg:border-b-0 lg:px-0 lg:py-4">
          <button
            type="button"
            className="z-99999 flex h-10 w-10 items-center justify-center rounded-lg border-gray-200 text-gray-500 dark:border-gray-800 dark:text-gray-400 lg:h-11 lg:w-11 lg:border"
            onClick={handleToggle}
            aria-label="Apri o chiudi la barra laterale"
          >
            {isMobileOpen ? (
              <CloseLineIcon className="h-6 w-6" />
            ) : (
              <ListIcon className="h-6 w-6" />
            )}
          </button>

          <Link to={routes.panoramica} className="flex items-center gap-2 font-semibold text-gray-800 dark:text-white/90 lg:hidden" aria-label="Panoramica Master Plan IT">
            <img src="/images/logo/logo-icon.svg" alt="" className="h-8 w-8" />
            Master Plan IT
          </Link>

          <button
            type="button"
            onClick={() => setApplicationMenuOpen((isOpen) => !isOpen)}
            className="z-99999 flex h-10 w-10 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden"
            aria-label="Apri o chiudi il menu applicativo"
            aria-expanded={isApplicationMenuOpen}
          >
            <MoreDotIcon className="h-6 w-6" />
          </button>
        </div>

        <div
          className={`${
            isApplicationMenuOpen ? "grid" : "hidden"
          } w-full grid-cols-2 items-center gap-3 px-5 py-4 shadow-theme-md lg:flex lg:justify-end lg:gap-4 lg:px-0 lg:shadow-none`}
        >
          <div className="col-span-2 flex items-center gap-2 2xsm:gap-3 lg:order-2 lg:col-span-1">
            <TenantDropdown />
            <PlanningYearDropdown />
          </div>
          <div className="lg:order-1">
            <ThemeToggleButton />
          </div>
          <div className="justify-self-end lg:order-3">
            <UserDropdown />
          </div>
        </div>
      </div>
    </header>
  );
};

export default AppHeader;
