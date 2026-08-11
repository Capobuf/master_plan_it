import { Navigate } from "react-router";
import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { firstAccessibleSettingsRoute } from "../../navigation/settingsNavigation";

export default function SettingsIndex() {
  const { loading, hasAbility } = useApplicationContext();
  if (loading) return <Alert variant="info" title="Caricamento delle impostazioni" message="Verifica delle autorizzazioni in corso." />;
  const target = firstAccessibleSettingsRoute(hasAbility);
  return target ? <Navigate to={target} replace /> : <Alert variant="warning" title="Impostazioni non disponibili" message="Non disponi di sezioni accessibili." />;
}
