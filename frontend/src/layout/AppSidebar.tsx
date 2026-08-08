import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Link, useLocation } from "react-router";

import { useApplicationContext } from "../context/ApplicationContext";
import { useSidebar } from "../context/SidebarContext";
import {
  ChevronDownIcon,
  HorizontaLDots,
} from "../icons";
import {
  applicationNavigation,
  isNavigationGroup,
  type ApplicationNavigationGroup,
  type ApplicationNavigationItem,
} from "../navigation/applicationNavigation";

function isActivePath(pathname: string, route: string): boolean {
  if (route === "/") {
    return pathname === "/";
  }

  return pathname === route || pathname.startsWith(`${route}/`);
}

function visibleItems(
  group: ApplicationNavigationGroup,
  hasAbility: (ability: string) => boolean,
): ApplicationNavigationItem[] {
  return group.items.filter((item) => hasAbility(item.requiredAbility));
}

const AppSidebar: React.FC = () => {
  const {
    isExpanded,
    isMobileOpen,
    isHovered,
    openSubmenu,
    setIsHovered,
    toggleMobileSidebar,
    toggleSubmenu,
  } = useSidebar();
  const { data, loading, hasAbility } = useApplicationContext();
  const location = useLocation();
  const [subMenuHeights, setSubMenuHeights] = useState<Record<string, number>>(
    {},
  );
  const subMenuRefs = useRef<Record<string, HTMLDivElement | null>>({});
  const previousPathname = useRef<string | null>(null);
  const expanded = isExpanded || isHovered || isMobileOpen;

  const visibleNavigation = useMemo(
    () =>
      applicationNavigation.reduce<
        Array<ApplicationNavigationItem | ApplicationNavigationGroup>
      >((entries, entry) => {
        if (isNavigationGroup(entry)) {
          const items = visibleItems(entry, hasAbility);

          if (items.length > 0) {
            entries.push({ ...entry, items });
          }
        } else if (hasAbility(entry.requiredAbility)) {
          entries.push(entry);
        }

        return entries;
      }, []),
    [hasAbility],
  );

  const isGroupActive = useCallback(
    (group: ApplicationNavigationGroup) =>
      group.items.some((item) => isActivePath(location.pathname, item.route)),
    [location.pathname],
  );

  useEffect(() => {
    if (previousPathname.current === location.pathname) {
      return;
    }

    previousPathname.current = location.pathname;
    const activeGroup = visibleNavigation.find(
      (entry): entry is ApplicationNavigationGroup =>
        isNavigationGroup(entry) && isGroupActive(entry),
    );

    if (activeGroup) {
      if (openSubmenu !== activeGroup.id) {
        toggleSubmenu(activeGroup.id);
      }
    } else if (openSubmenu !== null) {
      toggleSubmenu(openSubmenu);
    }
  }, [
    isGroupActive,
    location.pathname,
    openSubmenu,
    toggleSubmenu,
    visibleNavigation,
  ]);

  useEffect(() => {
    if (openSubmenu === null) {
      return;
    }

    const submenu = subMenuRefs.current[openSubmenu];
    if (submenu) {
      const nextHeight = submenu.scrollHeight;
      setSubMenuHeights((heights) =>
        heights[openSubmenu] === nextHeight
          ? heights
          : { ...heights, [openSubmenu]: nextHeight },
      );
    }
  }, [expanded, openSubmenu, visibleNavigation]);

  const handleItemClick = () => {
    if (isMobileOpen) {
      toggleMobileSidebar();
    }
  };

  return (
    <aside
      className={`fixed top-0 left-0 z-50 flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900 lg:mt-0
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
        className={`flex py-8 ${
          !isExpanded && !isHovered ? "lg:justify-center" : "justify-start"
        }`}
      >
        <Link to="/" onClick={handleItemClick} aria-label="Master Plan IT home">
          {expanded ? (
            <>
              <img
                className="dark:hidden"
                src="/images/logo/logo.svg"
                alt="Master Plan IT"
                width={150}
                height={40}
              />
              <img
                className="hidden dark:block"
                src="/images/logo/logo-dark.svg"
                alt="Master Plan IT"
                width={150}
                height={40}
              />
            </>
          ) : (
            <img
              src="/images/logo/logo-icon.svg"
              alt="Master Plan IT"
              width={32}
              height={32}
            />
          )}
        </Link>
      </div>

      <div className="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <nav className="mb-6" aria-label="Application navigation">
          <div className="mb-5">
            {expanded ? (
              <div className="mb-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                <span className="block text-xs uppercase text-gray-400">
                  Tenant
                </span>
                <span className="mt-1 block truncate text-sm font-medium text-gray-700 dark:text-gray-300">
                  {data?.tenant?.name ?? "Platform administration"}
                </span>
                {data?.tenant ? (
                  <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                    {data.tenant.state}
                  </span>
                ) : null}
              </div>
            ) : null}

            <h2
              className={`mb-4 flex text-xs uppercase leading-5 text-gray-400 ${
                !isExpanded && !isHovered
                  ? "lg:justify-center"
                  : "justify-start"
              }`}
            >
              {expanded ? "Menu" : <HorizontaLDots className="size-6" />}
            </h2>

            {loading ? null : (
              <ul className="flex flex-col gap-3">
                {visibleNavigation.map((entry) => {
                  if (!isNavigationGroup(entry)) {
                    const Icon = entry.icon;
                    const active = isActivePath(location.pathname, entry.route);

                    return (
                      <li key={entry.route}>
                        <Link
                          to={entry.route}
                          onClick={handleItemClick}
                          className={`menu-item group ${
                            active
                              ? "menu-item-active"
                              : "menu-item-inactive"
                          }`}
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
                            <span className="menu-item-text">{entry.label}</span>
                          ) : null}
                        </Link>
                      </li>
                    );
                  }

                  const active = isGroupActive(entry);
                  const isOpen = openSubmenu === entry.id;
                  const Icon = entry.icon;

                  return (
                    <li key={entry.id}>
                      <button
                        type="button"
                        onClick={() => toggleSubmenu(entry.id)}
                        className={`menu-item group w-full cursor-pointer ${
                          active || isOpen
                            ? "menu-item-active"
                            : "menu-item-inactive"
                        } ${
                          !isExpanded && !isHovered
                            ? "lg:justify-center"
                            : "lg:justify-start"
                        }`}
                        aria-expanded={isOpen}
                        aria-controls={`submenu-${entry.id}`}
                      >
                        <span
                          className={`menu-item-icon-size ${
                            active || isOpen
                              ? "menu-item-icon-active"
                              : "menu-item-icon-inactive"
                          }`}
                        >
                          <Icon />
                        </span>
                        {expanded ? (
                          <>
                            <span className="menu-item-text">{entry.label}</span>
                            <ChevronDownIcon
                              className={`ml-auto h-5 w-5 transition-transform duration-200 ${
                                isOpen ? "rotate-180 text-brand-500" : ""
                              }`}
                            />
                          </>
                        ) : null}
                      </button>

                      {expanded ? (
                        <div
                          id={`submenu-${entry.id}`}
                          ref={(element) => {
                            subMenuRefs.current[entry.id] = element;
                          }}
                          className="overflow-hidden transition-all duration-300"
                          style={{
                            height: isOpen
                              ? `${subMenuHeights[entry.id] ?? 0}px`
                              : "0px",
                          }}
                        >
                          <ul className="mt-2 ml-9 space-y-1">
                            {entry.items.map((item) => {
                              const itemActive = isActivePath(
                                location.pathname,
                                item.route,
                              );

                              return (
                                <li key={item.route}>
                                  <Link
                                    to={item.route}
                                    onClick={handleItemClick}
                                    className={`menu-dropdown-item ${
                                      itemActive
                                        ? "menu-dropdown-item-active"
                                        : "menu-dropdown-item-inactive"
                                    }`}
                                  >
                                    <span className="truncate">{item.label}</span>
                                  </Link>
                                </li>
                              );
                            })}
                          </ul>
                        </div>
                      ) : null}
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        </nav>
      </div>
    </aside>
  );
};

export default AppSidebar;
