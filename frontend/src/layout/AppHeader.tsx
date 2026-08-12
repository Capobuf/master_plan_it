import { useState } from "react";
import { Link } from "react-router";

import WorkspaceContextBar from "../components/header/WorkspaceContextBar";
import UserDropdown from "../components/header/UserDropdown";
import { useApplicationContext } from "../context/ApplicationContext";
import { applicationNavigation, canAccessApplicationNavigationItem, type ApplicationNavigationItem } from "../navigation/applicationNavigation";
import { routes } from "../navigation/routes";

const AppHeader: React.FC = () => {
  const { hasAbility } = useApplicationContext();
  const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
  const destinations: ApplicationNavigationItem[] = applicationNavigation.flatMap((section) => [...section.items] as ApplicationNavigationItem[])
    .filter((item) => canAccessApplicationNavigationItem(item, hasAbility));
  return (
    <header className="sticky top-0 z-99999 flex w-full border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 lg:border-b">
      <div className="flex grow flex-col items-center justify-between lg:flex-row lg:px-6">
        <div className="flex w-full flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 dark:border-gray-800 sm:gap-4 lg:justify-normal lg:border-b-0 lg:px-0 lg:py-4">
          <Link to={routes.panoramica} className="flex items-center gap-2 font-semibold text-gray-800 dark:text-white/90" aria-label="Panoramica Master Plan IT">
            <img src="/images/logo/logo-icon.svg" alt="" className="h-8 w-8" />
            Master Plan IT
          </Link>

          <button
            type="button"
            className="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.06] lg:hidden"
            aria-controls="application-mobile-navigation"
            aria-expanded={mobileNavigationOpen}
            onClick={() => setMobileNavigationOpen((open) => !open)}
          >
            Menu
          </button>

          <nav
            id="application-mobile-navigation"
            aria-label="Destinazioni applicative"
            className={`${mobileNavigationOpen ? "flex" : "hidden"} order-3 basis-full flex-col gap-1 pt-2 lg:order-none lg:flex lg:basis-auto lg:flex-row lg:flex-wrap lg:items-center lg:pt-0`}
          >
            {destinations.map((destination) => <Link key={destination.route} to={destination.route} onClick={() => setMobileNavigationOpen(false)} className="rounded-lg px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:text-gray-300 dark:hover:bg-white/[0.06] dark:hover:text-white">{destination.label}</Link>)}
          </nav>
        </div>

        <div
          className="flex w-full flex-wrap items-center justify-end gap-3 px-3 py-3 lg:px-0 lg:py-4"
        >
          <div className="col-span-2 lg:order-2 lg:col-span-1"><WorkspaceContextBar /></div>
          <div className="col-span-2 justify-self-end lg:order-3 lg:col-span-1">
            <UserDropdown />
          </div>
        </div>
      </div>
    </header>
  );
};

export default AppHeader;
