import type { ComponentType, SVGProps } from "react";

import { GridIcon } from "../icons";

export interface ApplicationNavigationItem {
  route: string;
  label: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  requiredAbility: string;
}

export const applicationNavigation = [
  {
    route: "/",
    label: "Dashboard",
    icon: GridIcon,
    requiredAbility: "dashboard.view",
  },
] as const satisfies readonly ApplicationNavigationItem[];
