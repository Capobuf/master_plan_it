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
