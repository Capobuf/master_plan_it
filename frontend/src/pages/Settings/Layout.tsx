import { Link, Outlet, useLocation } from "react-router";

import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import {
  accessibleSettingsSections,
  settingsSections,
} from "../../navigation/settingsNavigation";

export default function SettingsLayout() {
  const { loading, hasAbility } = useApplicationContext();
  const location = useLocation();

  if (loading) {
    return (
      <Alert
        variant="info"
        title="Caricamento delle impostazioni"
        message="Verifica delle sezioni disponibili in corso."
      />
    );
  }

  const accessibleSections = accessibleSettingsSections(hasAbility);

  if (accessibleSections.length === 0) {
    return (
      <Alert
        variant="warning"
        title="Impostazioni non disponibili"
        message="Non disponi dell'autorizzazione necessaria per accedere alle impostazioni."
      />
    );
  }

  const requestedSection = settingsSections.find(
    (section) => section.route === location.pathname,
  );
  const requestedSectionAllowed = requestedSection
    ? requestedSection.abilities.some(hasAbility)
    : true;

  return (
    <div className="space-y-6">
      <nav
        aria-label="Sezioni Impostazioni"
        className="overflow-x-auto border-b border-gray-200 dark:border-gray-800"
      >
        <div className="flex min-w-max gap-1">
          {accessibleSections.map((section) => {
            const active = section.route === location.pathname;

            return (
              <Link
                key={section.route}
                to={section.route}
                aria-current={active ? "page" : undefined}
                className={`border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                  active
                    ? "border-brand-500 text-brand-600 dark:text-brand-400"
                    : "border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-200"
                }`}
              >
                {section.label}
              </Link>
            );
          })}
        </div>
      </nav>

      {requestedSectionAllowed ? (
        <Outlet />
      ) : (
        <Alert
          variant="warning"
          title="Sezione non disponibile"
          message="Non disponi dell'autorizzazione necessaria per visualizzare questa sezione."
        />
      )}
    </div>
  );
}
