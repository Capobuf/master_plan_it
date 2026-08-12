import type { ComponentType, SVGProps } from "react";

import {
  BoxCubeIcon,
  DocsIcon,
  DollarLineIcon,
  GridIcon,
  GroupIcon,
  PieChartIcon,
  TableIcon,
  TaskIcon,
} from "../icons";
import { routes } from "./routes";

export interface ApplicationNavigationItem {
  route: string;
  label: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  requiredAbility: string | readonly string[];
}

export interface ApplicationNavigationSection {
  id: string;
  label: string;
  items: readonly ApplicationNavigationItem[];
}

export function canAccessApplicationNavigationItem(
  item: ApplicationNavigationItem,
  hasAbility: (ability: string) => boolean,
): boolean {
  return typeof item.requiredAbility === "string"
    ? hasAbility(item.requiredAbility)
    : item.requiredAbility.some(hasAbility);
}

export const applicationNavigation = [
  {
    id: "general",
    label: "Generale",
    items: [
      {
        route: routes.panoramica,
        label: "Panoramica",
        icon: GridIcon,
        requiredAbility: "dashboard.view",
      },
    ],
  },
  {
    id: "planning",
    label: "Pianificazione",
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
    items: [
      {
        route: routes.spese,
        label: "Spese",
        icon: TableIcon,
        requiredAbility: "expense.view",
      },
      {
        route: routes.plafonds,
        label: "Plafond",
        icon: DollarLineIcon,
        requiredAbility: "expense.view",
      },
      {
        route: routes.contratti,
        label: "Contratti",
        icon: DocsIcon,
        requiredAbility: "contract.view",
      },
      {
        route: routes.progetti,
        label: "Progetti",
        icon: PieChartIcon,
        requiredAbility: "project.view",
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
    items: [
      {
        route: routes.impostazioni,
        label: "Impostazioni",
        icon: TaskIcon,
        requiredAbility: [
          "tenant-settings.view",
          "tenant-settings.update",
          "platform.users.manage",
          "platform.roles.manage",
          "planning-year.view",
          "cost-center.view",
        ],
      },
    ],
  },
  {
    id: "platform",
    label: "Piattaforma",
    items: [
      {
        route: routes.tenant,
        label: "Tenant",
        icon: BoxCubeIcon,
        requiredAbility: "platform.tenants.view",
      },
    ],
  },
] as const satisfies readonly ApplicationNavigationSection[];
