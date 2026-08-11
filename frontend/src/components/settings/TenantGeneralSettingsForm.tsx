import { useCallback, useEffect, useState } from "react";

import {
  getTenantSettings,
  updateTenantSettings,
  type TenantSettings,
} from "../../api/tenantSettings";
import { ApiError } from "../../api/client";
import { useApplicationContext } from "../../context/ApplicationContext";
import { normalizeDecimalInput } from "../../presentation/formatters";
import ComponentCard from "../common/ComponentCard";
import Label from "../form/Label";
import Checkbox from "../form/input/Checkbox";
import DecimalInput from "../form/input/DecimalInput";
import InputField from "../form/input/InputField";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";

interface TenantGeneralSettingsFormProps {
  canUpdate: boolean;
}

export default function TenantGeneralSettingsForm({
  canUpdate,
}: TenantGeneralSettingsFormProps) {
  const { refreshContext } = useApplicationContext();
  const [settings, setSettings] = useState<TenantSettings | null>(null);
  const [initialSettings, setInitialSettings] = useState<TenantSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const loaded = await getTenantSettings();
      setSettings(loaded);
      setInitialSettings(loaded);
    } catch (requestError) {
      setError(ApiError.from(requestError).message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!settings || !canUpdate) return;

    setSaving(true);
    setError(null);

    try {
      const saved = await updateTenantSettings({
        name: settings.name,
        timezone: settings.timezone,
        default_vat_rate: normalizeDecimalInput(settings.default_vat_rate, 2),
        budget_basis: settings.budget_basis,
        deletion_reason_required: settings.deletion_reason_required,
        lock_version: settings.lock_version,
      });
      setSettings(saved);
      setInitialSettings(saved);
      await refreshContext();
    } catch (requestError) {
      setError(ApiError.from(requestError).message);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <Alert
        variant="info"
        title="Caricamento delle impostazioni"
        message="Recupero delle impostazioni del Tenant in corso."
      />
    );
  }

  if (!settings) {
    return (
      <Alert
        variant="error"
        title="Impostazioni non disponibili"
        message={error ?? "Non è stato possibile recuperare le impostazioni del Tenant."}
      />
    );
  }

  const disabled = !canUpdate || saving;

  return (
    <form className="space-y-6" onSubmit={(event) => void save(event)}>
      {error ? (
        <Alert
          variant="error"
          title="Salvataggio non riuscito"
          message={error}
        />
      ) : null}

      <ComponentCard
        title="Informazioni Tenant"
        desc="Il nome identifica il Tenant nelle superfici applicative."
      >
        <div>
          <Label htmlFor="tenant-settings-name">Nome operativo</Label>
          <InputField
            id="tenant-settings-name"
            value={settings.name}
            onChange={(event) =>
              setSettings((current) =>
                current ? { ...current, name: event.target.value } : current,
              )
            }
            disabled={disabled}
          />
        </div>
      </ComponentCard>

      <ComponentCard
        title="Impostazioni economiche"
        desc="Definiscono i valori predefiniti usati dalle nuove operazioni."
      >
        <div className="grid gap-5 md:grid-cols-2">
          <div>
            <Label htmlFor="tenant-settings-vat">IVA predefinita</Label>
            <DecimalInput
              id="tenant-settings-vat"
              value={settings.default_vat_rate}
              onChange={(default_vat_rate) =>
                setSettings((current) =>
                  current ? { ...current, default_vat_rate } : current,
                )
              }
              fixedScale={2}
              suffix="%"
              disabled={disabled}
            />
            <p className="mt-1.5 text-xs leading-5 text-gray-500 dark:text-gray-400">
              Si applica solo ai nuovi elementi senza aliquota esplicita. I dati già salvati non vengono modificati.
            </p>
          </div>

          <div>
            <Label htmlFor="tenant-settings-budget-basis">Base Budget ufficiale</Label>
            <select
              id="tenant-settings-budget-basis"
              value={settings.budget_basis}
              onChange={(event) =>
                setSettings((current) =>
                  current
                    ? { ...current, budget_basis: event.target.value as "net" | "gross" }
                    : current,
                )
              }
              disabled={disabled || settings.budget_basis_locked}
              className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 shadow-theme-xs disabled:cursor-not-allowed disabled:bg-gray-100 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:disabled:bg-gray-800"
            >
              <option value="net">Netto</option>
              <option value="gross">Lordo</option>
            </select>
            {settings.budget_basis_locked ? (
              <p className="mt-1.5 text-xs leading-5 text-warning-600 dark:text-warning-400">
                La Base Budget è bloccata dopo la prima approvazione e non può più essere modificata.
              </p>
            ) : null}
          </div>

          <div>
            <Label htmlFor="tenant-settings-currency">Valuta</Label>
            <InputField
              id="tenant-settings-currency"
              value={settings.currency_code}
              disabled
            />
            <p className="mt-1.5 text-xs leading-5 text-gray-500 dark:text-gray-400">
              La valuta è informativa e non è modificabile da questa pagina.
            </p>
          </div>
        </div>
      </ComponentCard>

      <ComponentCard title="Localizzazione">
        <div>
          <Label htmlFor="tenant-settings-timezone">Fuso orario</Label>
          <InputField
            id="tenant-settings-timezone"
            value={settings.timezone}
            onChange={(event) =>
              setSettings((current) =>
                current ? { ...current, timezone: event.target.value } : current,
              )
            }
            disabled={disabled}
          />
        </div>
      </ComponentCard>

      <ComponentCard title="Operazioni">
        <Checkbox
          id="tenant-settings-deletion-reason"
          label="Richiedi una motivazione per le eliminazioni future"
          checked={settings.deletion_reason_required}
          onChange={(deletion_reason_required) =>
            setSettings((current) =>
              current ? { ...current, deletion_reason_required } : current,
            )
          }
          disabled={disabled}
        />
        <p className="text-xs leading-5 text-gray-500 dark:text-gray-400">
          La modifica non riscrive eliminazioni o revisioni già registrate.
        </p>
      </ComponentCard>

      {canUpdate ? (
        <div className="flex flex-wrap justify-end gap-3">
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              setSettings(initialSettings);
              setError(null);
            }}
            disabled={saving}
          >
            Annulla
          </Button>
          <Button disabled={saving}>
            {saving ? "Salvataggio…" : "Salva modifiche"}
          </Button>
        </div>
      ) : null}
    </form>
  );
}
