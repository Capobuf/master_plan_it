import { Navigate } from "react-router";

import Alert from "../../components/ui/alert/Alert";
import { useApplicationContext } from "../../context/ApplicationContext";
import { firstAccessibleSettingsRoute } from "../../navigation/settingsNavigation";

export default function SettingsIndex() {
  const { loading, hasAbility } = useApplicationContext();

  if (loading) {
    return (
      <Alert
        variant="info"
        title="Caricamento delle impostazioni"
        message="Verifica delle sezioni disponibili in corso."
      />
    );
  }

  const destination = firstAccessibleSettingsRoute(hasAbility);

  if (destination === null) {
    return (
      <Alert
        variant="warning"
        title="Impostazioni non disponibili"
        message="Non disponi dell'autorizzazione necessaria per accedere alle impostazioni."
      />
    );
  }

  return <Navigate to={destination} replace />;
}
