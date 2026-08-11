import { describe, expect, it } from "vitest";
import { routes } from "./routes";
import {
  accessibleSettingsSections,
  firstAccessibleSettingsRoute,
} from "./settingsNavigation";

const abilities = (...allowed: string[]) => (ability: string) =>
  allowed.includes(ability);

describe("settings navigation", () => {
  it("redirects to the first accessible section in stable order", () => {
    expect(firstAccessibleSettingsRoute(abilities("tenant-users.view"))).toBe(
      routes.impostazioniUtenti,
    );
    expect(firstAccessibleSettingsRoute(abilities("tenant-roles.manage"))).toBe(
      routes.impostazioniRuoli,
    );
    expect(firstAccessibleSettingsRoute(abilities("cost-center.view"))).toBe(
      routes.impostazioniCentri,
    );
    expect(firstAccessibleSettingsRoute(abilities())).toBeNull();
  });

  it("hides unauthorized sections and treats update or manage as access", () => {
    const sections = accessibleSettingsSections(
      abilities("tenant-settings.update", "tenant-users.manage"),
    );

    expect(sections.map((section) => section.label)).toEqual([
      "Generali",
      "Utenti",
    ]);
  });
});
