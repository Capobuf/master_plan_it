import { describe, expect, it } from "vitest";

import {
  applicationNavigation,
  canAccessApplicationNavigationItem,
  type ApplicationNavigationItem,
} from "./applicationNavigation";
import { routes } from "./routes";

const settingsItems = applicationNavigation.find(
  (section) => section.id === "settings",
)?.items ?? [];

describe("application navigation", () => {
  it("uses distinct icons for Overview, Settings, and Tenant", () => {
    const overview = applicationNavigation.find(
      (section) => section.id === "general",
    )?.items[0];
    const settings = applicationNavigation.find(
      (section) => section.id === "settings",
    )?.items[0];
    const tenant = applicationNavigation.find(
      (section) => section.id === "platform",
    )?.items[0];

    expect(new Set([overview?.icon, settings?.icon, tenant?.icon])).toHaveProperty(
      "size",
      3,
    );
  });

  it("exposes one composite Settings item instead of five destinations", () => {
    expect(settingsItems).toHaveLength(1);
    expect(settingsItems[0]).toMatchObject({
      route: routes.impostazioni,
      label: "Impostazioni",
    });
  });

  it.each([
    "tenant-settings.view",
    "tenant-settings.update",
    "platform.users.manage",
    "platform.roles.manage",
    "planning-year.view",
    "cost-center.view",
  ])("shows Settings with the %s section ability", (allowedAbility) => {
    expect(
      canAccessApplicationNavigationItem(
        settingsItems[0] as ApplicationNavigationItem,
        (ability) => ability === allowedAbility,
      ),
    ).toBe(true);
  });

  it("hides Settings when no section is accessible", () => {
    expect(
      canAccessApplicationNavigationItem(
        settingsItems[0] as ApplicationNavigationItem,
        () => false,
      ),
    ).toBe(false);
  });
});
