import { Link, Outlet, useLocation } from "react-router";
import { useApplicationContext } from "../../context/ApplicationContext";
import { accessibleSettingsSections } from "../../navigation/settingsNavigation";

export default function SettingsLayout() {
  const { hasAbility } = useApplicationContext(); const location = useLocation();
  const sections = accessibleSettingsSections(hasAbility);
  return <div className="space-y-5"><nav aria-label="Sezioni Impostazioni" className="flex gap-2 overflow-x-auto rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-800 dark:bg-gray-900">{sections.map(section=><Link key={section.route} to={section.route} className={`whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium ${location.pathname===section.route?"bg-brand-500 text-white":"text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"}`}>{section.label}</Link>)}</nav><Outlet /></div>;
}
