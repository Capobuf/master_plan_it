import type { ComponentType, SVGProps } from "react";

import {
  DocsIcon,
  DollarLineIcon,
  GridIcon,
  GroupIcon,
  LockIcon,
  PieChartIcon,
  TableIcon,
} from "../icons";
import { routes } from "./routes";

export interface ApplicationNavigationItem {
  route: string;
  label: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  requiredAbility?: string;
  requiredAbilities?: readonly string[];
}

export interface ApplicationNavigationSection {
  id: string;
  label: string;
  items: readonly ApplicationNavigationItem[];
}

export const applicationNavigation: readonly ApplicationNavigationSection[] = [
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
      { route: routes.audit, label: "Audit", icon: DocsIcon, requiredAbility: "audit.view" },
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
        icon: LockIcon,
        requiredAbilities: ["tenant-settings.view","tenant-users.view","tenant-users.manage","tenant-roles.view","tenant-roles.manage","planning-year.view","cost-center.view"],
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
        icon: GridIcon,
        requiredAbility: "platform.tenants.view",
      },
      { route: routes.piattaformaOverview, label: "Panoramica operativa", icon: GridIcon, requiredAbility: "platform.settings.manage" },
      { route: routes.piattaformaSettings, label: "Impostazioni piattaforma", icon: LockIcon, requiredAbility: "platform.settings.manage" },
      { route: routes.auditGlobale, label: "Audit globale", icon: DocsIcon, requiredAbility: "platform.audit.view-global" },
    ],
  },
] as const satisfies readonly ApplicationNavigationSection[];
