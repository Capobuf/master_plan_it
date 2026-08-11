import { describe, expect, it } from "vitest";
import { applicationNavigation } from "./applicationNavigation";
import { routes } from "./routes";

const visibleRoutes = (...abilities: string[]) => applicationNavigation
  .flatMap((section) => section.items)
  .filter((item) => item.requiredAbility
    ? abilities.includes(item.requiredAbility)
    : item.requiredAbilities?.some((ability) => abilities.includes(ability)))
  .map((item) => item.route);

describe("applicationNavigation", () => {
  it("shows Settings but never platform Tenant management to a tenant user", () => {
    const routesForTenantUser = visibleRoutes("tenant-users.view");

    expect(routesForTenantUser).toContain(routes.impostazioni);
    expect(routesForTenantUser).not.toContain(routes.tenant);
  });

  it("keeps platform Tenant management visible to Administrator abilities", () => {
    expect(visibleRoutes("platform.tenants.view")).toContain(routes.tenant);
  });
});
