import { useState } from "react";
import { Link } from "react-router";

import { ThemeToggleButton } from "../components/common/ThemeToggleButton";
import TenantDropdown from "../components/header/TenantDropdown";
import UserDropdown from "../components/header/UserDropdown";
import { useSidebar } from "../context/SidebarContext";
import { CloseLineIcon, ListIcon, MoreDotIcon } from "../icons";

const AppHeader: React.FC = () => {
  const [isApplicationMenuOpen, setApplicationMenuOpen] = useState(false);
  const { isMobileOpen, toggleSidebar, toggleMobileSidebar } = useSidebar();

  const handleToggle = () => {
    if (window.innerWidth >= 1024) {
      toggleSidebar();
    } else {
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
            aria-label="Toggle sidebar"
          >
            {isMobileOpen ? (
              <CloseLineIcon className="h-6 w-6" />
            ) : (
              <ListIcon className="h-6 w-6" />
            )}
          </button>

          <Link to="/" className="lg:hidden" aria-label="Master Plan IT home">
            <img
              className="dark:hidden"
              src="/images/logo/logo.svg"
              alt="Master Plan IT"
            />
            <img
              className="hidden dark:block"
              src="/images/logo/logo-dark.svg"
              alt="Master Plan IT"
            />
          </Link>

          <button
            type="button"
            onClick={() => setApplicationMenuOpen((isOpen) => !isOpen)}
            className="z-99999 flex h-10 w-10 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden"
            aria-label="Toggle application menu"
            aria-expanded={isApplicationMenuOpen}
          >
            <MoreDotIcon className="h-6 w-6" />
          </button>
        </div>

        <div
          className={`${
            isApplicationMenuOpen ? "flex" : "hidden"
          } w-full items-center justify-between gap-4 px-5 py-4 shadow-theme-md lg:flex lg:justify-end lg:px-0 lg:shadow-none`}
        >
          <div className="flex items-center gap-2 2xsm:gap-3">
            <ThemeToggleButton />
            <TenantDropdown />
          </div>
          <UserDropdown />
        </div>
      </div>
    </header>
  );
};

export default AppHeader;
