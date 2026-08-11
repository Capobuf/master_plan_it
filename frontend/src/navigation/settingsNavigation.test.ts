import { describe, expect, it } from "vitest";

import { routes } from "./routes";
import {
  accessibleSettingsSections,
  firstAccessibleSettingsRoute,
  settingsSections,
} from "./settingsNavigation";

const abilities = (...allowed: string[]) => (ability: string) =>
  allowed.includes(ability);

describe("settings navigation", () => {
  it("defines the five sections in their canonical order", () => {
    expect(
      settingsSections.map(({ label, route }) => ({ label, route })),
    ).toEqual([
      { label: "Generali", route: routes.impostazioniGenerali },
      { label: "Utenti", route: routes.impostazioniUtenti },
      { label: "Ruoli e permessi", route: routes.impostazioniRuoli },
      {
        label: "Anni di pianificazione",
        route: routes.impostazioniAnni,
      },
      { label: "Centri di costo", route: routes.impostazioniCentri },
    ]);
  });

  it("filters sections with OR semantics over their current abilities", () => {
    expect(
      accessibleSettingsSections(
        abilities(
          "tenant-settings.update",
          "platform.roles.manage",
          "cost-center.view",
        ),
      ).map((section) => section.label),
    ).toEqual(["Generali", "Ruoli e permessi", "Centri di costo"]);

    expect(
      accessibleSettingsSections(
        abilities(
          "tenant-settings.view",
          "platform.users.manage",
          "planning-year.view",
        ),
      ).map((section) => section.label),
    ).toEqual(["Generali", "Utenti", "Anni di pianificazione"]);
  });

  it("selects the first accessible route in stable order", () => {
    expect(
      firstAccessibleSettingsRoute(
        abilities("cost-center.view", "platform.users.manage"),
      ),
    ).toBe(routes.impostazioniUtenti);
    expect(
      firstAccessibleSettingsRoute(
        abilities("planning-year.view", "platform.roles.manage"),
      ),
    ).toBe(routes.impostazioniRuoli);
    expect(
      firstAccessibleSettingsRoute(abilities("planning-year.view")),
    ).toBe(routes.impostazioniAnni);
    expect(firstAccessibleSettingsRoute(abilities())).toBeNull();
  });
});
