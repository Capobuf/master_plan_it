export const routes = {
  accesso: "/accesso",
  panoramica: "/",
  budget: "/budget",
  report: "/report",
  spese: "/spese",
  nuovaSpesa: "/spese/nuova",
  spesa: (expenseId: number | string) => `/spese/${expenseId}`,
  modificaSpesa: (expenseId: number | string) => `/spese/${expenseId}/modifica`,
  contratti: "/contratti",
  nuovoContratto: "/contratti/nuovo",
  contratto: (contractId: number | string) => `/contratti/${contractId}`,
  modificaContratto: (contractId: number | string) => `/contratti/${contractId}/modifica`,
  fornitori: "/fornitori",
  centriDiCosto: "/centri-di-costo",
  anniDiPianificazione: "/anni-di-pianificazione",
  utenti: "/utenti",
  ruoli: "/ruoli",
  tenant: "/tenant",
} as const;

export const routePatterns = {
  spesa: "/spese/:expenseId",
  modificaSpesa: "/spese/:expenseId/modifica",
  contratto: "/contratti/:contractId",
  modificaContratto: "/contratti/:contractId/modifica",
} as const;

export const legacyRoutes = {
  accesso: "/signin",
  report: "/reports",
  spese: "/expenses",
  contratti: "/contracts",
  fornitori: "/vendors",
  centriDiCosto: "/cost-centers",
  anniDiPianificazione: "/planning-years",
  utenti: "/users",
  ruoli: "/roles",
  tenant: "/tenants",
} as const;

export const legacyRoutePatterns = {
  nuovaSpesa: "/expenses/new",
  spesa: "/expenses/:expenseId",
  modificaSpesa: "/expenses/:expenseId/edit",
  nuovoContratto: "/contracts/new",
  contratto: "/contracts/:contractId",
  modificaContratto: "/contracts/:contractId/edit",
} as const;
