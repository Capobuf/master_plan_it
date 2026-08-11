import { routes } from "./routes";

export const settingsSections = [
  { label: "Generali", route: routes.impostazioniGenerali, abilities: ["tenant-settings.view", "tenant-settings.update"] },
  { label: "Utenti", route: routes.impostazioniUtenti, abilities: ["tenant-users.view", "tenant-users.manage"] },
  { label: "Ruoli e Permessi", route: routes.impostazioniRuoli, abilities: ["tenant-roles.view", "tenant-roles.manage"] },
  { label: "Anni di pianificazione", route: routes.impostazioniAnni, abilities: ["planning-year.view"] },
  { label: "Centri di costo", route: routes.impostazioniCentri, abilities: ["cost-center.view"] },
] as const;

export function accessibleSettingsSections(hasAbility: (ability: string) => boolean) {
  return settingsSections.filter((section) => section.abilities.some(hasAbility));
}

export function firstAccessibleSettingsRoute(hasAbility: (ability: string) => boolean): string | null {
  return accessibleSettingsSections(hasAbility)[0]?.route ?? null;
}
