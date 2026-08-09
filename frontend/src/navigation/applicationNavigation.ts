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
import { routes } from "./routes";

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
    route: routes.panoramica,
    label: "Panoramica",
    icon: GridIcon,
    requiredAbility: "dashboard.view",
  },
  {
    id: "planning",
    label: "Pianificazione",
    icon: PieChartIcon,
    items: [
      {
        route: routes.budget,
        label: "Budget",
        icon: DollarLineIcon,
        requiredAbility: "budget.view",
      },
      {
        route: routes.report,
        label: "Report",
        icon: PieChartIcon,
        requiredAbility: "report.view",
      },
    ],
  },
  {
    id: "operations",
    label: "Operatività",
    icon: TableIcon,
    items: [
      {
        route: routes.spese,
        label: "Spese",
        icon: TableIcon,
        requiredAbility: "expense.view",
      },
      {
        route: routes.contratti,
        label: "Contratti",
        icon: DocsIcon,
        requiredAbility: "contract.view",
      },
      {
        route: routes.fornitori,
        label: "Fornitori",
        icon: GroupIcon,
        requiredAbility: "vendor.view",
      },
    ],
  },
  {
    id: "settings",
    label: "Impostazioni",
    icon: BoxCubeIcon,
    items: [
      {
        route: routes.anniDiPianificazione,
        label: "Anni di pianificazione",
        icon: CalenderIcon,
        requiredAbility: "planning-year.view",
      },
      {
        route: routes.centriDiCosto,
        label: "Centri di costo",
        icon: BoxCubeIcon,
        requiredAbility: "cost-center.view",
      },
      {
        route: routes.utenti,
        label: "Utenti",
        icon: UserCircleIcon,
        requiredAbility: "platform.users.manage",
      },
      {
        route: routes.ruoli,
        label: "Ruoli",
        icon: LockIcon,
        requiredAbility: "platform.roles.manage",
      },
    ],
  },
  {
    id: "platform",
    label: "Piattaforma",
    icon: GridIcon,
    items: [
      {
        route: routes.tenant,
        label: "Tenant",
        icon: GridIcon,
        requiredAbility: "platform.tenants.view",
      },
    ],
  },
] as const satisfies readonly ApplicationNavigationEntry[];
