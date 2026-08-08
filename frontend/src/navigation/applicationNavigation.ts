import type { ComponentType, SVGProps } from "react";

import {
  BoxCubeIcon,
  CalenderIcon,
  DocsIcon,
  DollarLineIcon,
  GridIcon,
  GroupIcon,
  LockIcon,
  PieChartIcon,
  TableIcon,
  UserCircleIcon,
} from "../icons";

export interface ApplicationNavigationItem {
  route: string;
  label: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  requiredAbility: string;
}

export interface ApplicationNavigationGroup {
  id: string;
  label: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  items: readonly ApplicationNavigationItem[];
}

export type ApplicationNavigationEntry =
  | ApplicationNavigationItem
  | ApplicationNavigationGroup;

export function isNavigationGroup(
  entry: ApplicationNavigationEntry,
): entry is ApplicationNavigationGroup {
  return "items" in entry;
}

export const applicationNavigation = [
  {
    route: "/",
    label: "Dashboard",
    icon: GridIcon,
    requiredAbility: "dashboard.view",
  },
  {
    id: "planning",
    label: "PIANIFICAZIONE",
    icon: PieChartIcon,
    items: [
      {
        route: "/budget",
        label: "Budget",
        icon: DollarLineIcon,
        requiredAbility: "budget.view",
      },
      {
        route: "/reports",
        label: "Report",
        icon: PieChartIcon,
        requiredAbility: "report.view",
      },
    ],
  },
  {
    id: "operations",
    label: "OPERATIVITÀ",
    icon: TableIcon,
    items: [
      {
        route: "/expenses",
        label: "Spese",
        icon: TableIcon,
        requiredAbility: "expense.view",
      },
      {
        route: "/contracts",
        label: "Contratti",
        icon: DocsIcon,
        requiredAbility: "contract.view",
      },
      {
        route: "/vendors",
        label: "Fornitori",
        icon: GroupIcon,
        requiredAbility: "vendor.view",
      },
    ],
  },
  {
    id: "settings",
    label: "IMPOSTAZIONI",
    icon: BoxCubeIcon,
    items: [
      {
        route: "/planning-years",
        label: "Anni di pianificazione",
        icon: CalenderIcon,
        requiredAbility: "planning-year.view",
      },
      {
        route: "/cost-centers",
        label: "Centri di costo",
        icon: BoxCubeIcon,
        requiredAbility: "cost-center.view",
      },
      {
        route: "/users",
        label: "Utenti",
        icon: UserCircleIcon,
        requiredAbility: "platform.users.manage",
      },
      {
        route: "/roles",
        label: "Ruoli",
        icon: LockIcon,
        requiredAbility: "platform.roles.manage",
      },
    ],
  },
  {
    id: "platform",
    label: "PIATTAFORMA",
    icon: GridIcon,
    items: [
      {
        route: "/tenants",
        label: "Tenant",
        icon: GridIcon,
        requiredAbility: "platform.tenants.view",
      },
    ],
  },
] as const satisfies readonly ApplicationNavigationEntry[];
