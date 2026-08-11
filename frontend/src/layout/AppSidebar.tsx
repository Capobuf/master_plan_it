import { useMemo } from "react";
import { Link, useLocation } from "react-router";

import { useApplicationContext } from "../context/ApplicationContext";
import { useSidebar } from "../context/SidebarContext";
import { CloseLineIcon, HorizontaLDots } from "../icons";
import {
  applicationNavigation,
  canAccessApplicationNavigationItem,
  type ApplicationNavigationSection,
} from "../navigation/applicationNavigation";
import { routes } from "../navigation/routes";

function isActivePath(pathname: string, route: string): boolean {
  if (route === "/") {
    return pathname === "/";
  }

  return pathname === route || pathname.startsWith(`${route}/`);
}

const AppSidebar: React.FC = () => {
  const {
    isExpanded,
    isMobileOpen,
    isHovered,
    setIsHovered,
    toggleMobileSidebar,
  } = useSidebar();
  const { loading, hasAbility } = useApplicationContext();
  const location = useLocation();
  const expanded = isExpanded || isHovered || isMobileOpen;

  const visibleSections = useMemo(
    () =>
      applicationNavigation.reduce<ApplicationNavigationSection[]>(
        (sections, section) => {
          const items = section.items.filter((item) =>
            canAccessApplicationNavigationItem(item, hasAbility),
          );

          if (items.length > 0) {
            sections.push({ ...section, items });
          }

          return sections;
        },
        [],
      ),
    [hasAbility],
  );

  const handleItemClick = () => {
    if (isMobileOpen) {
      toggleMobileSidebar();
    }
  };

  return (
    <aside
      className={`fixed top-0 left-0 z-[100000] flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900 lg:mt-0
        ${
          isExpanded || isMobileOpen
            ? "w-[290px]"
            : isHovered
              ? "w-[290px]"
              : "w-[90px]"
        }
        ${isMobileOpen ? "translate-x-0" : "-translate-x-full"}
        lg:translate-x-0`}
      onMouseEnter={() => !isExpanded && setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      <div
        className={`flex items-center py-8 ${
          !isExpanded && !isHovered ? "lg:justify-center" : "justify-between"
        }`}
      >
        <Link
          to={routes.panoramica}
          onClick={handleItemClick}
          aria-label="Panoramica Master Plan IT"
        >
          {expanded ? (
            <span className="flex items-center gap-3 text-lg font-semibold text-gray-800 dark:text-white/90">
              <img src="/images/logo/logo-icon.svg" alt="" width={32} height={32} />
              Master Plan IT
            </span>
          ) : (
            <img
              src="/images/logo/logo-icon.svg"
              alt="Master Plan IT"
              width={32}
              height={32}
            />
          )}
        </Link>
        {isMobileOpen ? (
          <button
            type="button"
            onClick={toggleMobileSidebar}
            className="flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden"
            aria-label="Chiudi la barra laterale"
          >
            <CloseLineIcon className="h-6 w-6" />
          </button>
        ) : null}
      </div>

      <div className="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <nav className="mb-6" aria-label="Navigazione applicativa">
          {loading ? null : (
            <div className="space-y-7">
              {visibleSections.map((section) => (
                <section key={section.id} aria-labelledby={`navigation-${section.id}`}>
                  <h2
                    id={`navigation-${section.id}`}
                    className={`mb-3 flex text-xs font-medium uppercase leading-5 tracking-wide text-gray-400 ${
                      !isExpanded && !isHovered
                        ? "lg:justify-center"
                        : "justify-start"
                    }`}
                  >
                    {expanded ? (
                      section.label
                    ) : (
                      <>
                        <span className="sr-only">{section.label}</span>
                        <HorizontaLDots className="size-6" aria-hidden="true" />
                      </>
                    )}
                  </h2>

                  <ul className="flex flex-col gap-2">
                    {section.items.map((item) => {
                      const Icon = item.icon;
                      const active = isActivePath(location.pathname, item.route);

                      return (
                        <li key={item.route}>
                          <Link
                            to={item.route}
                            onClick={handleItemClick}
                            className={`menu-item group ${
                              active ? "menu-item-active" : "menu-item-inactive"
                            } ${
                              !isExpanded && !isHovered
                                ? "lg:justify-center"
                                : "lg:justify-start"
                            }`}
                            aria-label={item.label}
                            aria-current={active ? "page" : undefined}
                          >
                            <span
                              className={`menu-item-icon-size ${
                                active
                                  ? "menu-item-icon-active"
                                  : "menu-item-icon-inactive"
                              }`}
                            >
                              <Icon />
                            </span>
                            {expanded ? (
                              <span className="menu-item-text">{item.label}</span>
                            ) : null}
                          </Link>
                        </li>
                      );
                    })}
                  </ul>
                </section>
              ))}
            </div>
          )}
        </nav>
      </div>
    </aside>
  );
};

export default AppSidebar;
