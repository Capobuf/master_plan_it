import { useState } from "react";
import { useNavigate } from "react-router";
import { ApiError } from "../../api/client";
import { useAuth } from "../../context/AuthContext";
import { useApplicationContext } from "../../context/ApplicationContext";
import { useTheme } from "../../context/ThemeContext";
import { ArrowRightIcon, ChevronDownIcon, UserCircleIcon } from "../../icons";
import { routes } from "../../navigation/routes";
import Alert from "../ui/alert/Alert";
import { Dropdown } from "../ui/dropdown/Dropdown";
import { DropdownItem } from "../ui/dropdown/DropdownItem";

function errorMessage(error: unknown): string {
  const apiError = ApiError.from(error);
  const isUnexpected = apiError.handledStatus === null || apiError.status === 500;

  if (isUnexpected && apiError.correlationId) {
    return `${apiError.message} Riferimento tecnico: ${apiError.correlationId}`;
  }

  return apiError.message;
}

export default function UserDropdown() {
  const [isOpen, setIsOpen] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [logoutError, setLogoutError] = useState<string | null>(null);
  const { currentUser, logout } = useAuth();
  const { data: applicationContext } = useApplicationContext();
  const { theme, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const roleContext = applicationContext?.platformAdministrator
    ? "Amministratore di Piattaforma"
    : applicationContext?.tenant
      ? "Utente del Tenant"
      : "Utente";

  const closeDropdown = () => setIsOpen(false);

  const selectTheme = (selectedTheme: "light" | "dark") => {
    if (theme !== selectedTheme) {
      toggleTheme();
    }
  };

  const handleLogout = async () => {
    if (isLoggingOut) {
      return;
    }

    setIsLoggingOut(true);
    setLogoutError(null);

    try {
      await logout();
      closeDropdown();
      navigate(routes.accesso, { replace: true });
    } catch (error) {
      setLogoutError(errorMessage(error));
    } finally {
      setIsLoggingOut(false);
    }
  };

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen((open) => !open)}
        className="flex items-center text-gray-700 dropdown-toggle dark:text-gray-400"
        aria-label="Apri il menu utente"
        aria-expanded={isOpen}
        aria-controls="user-dropdown"
      >
        <span className="mr-3 flex h-11 w-11 items-center justify-center overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
          <UserCircleIcon className="h-7 w-7 fill-gray-500 dark:fill-gray-400" />
        </span>

        <span className="block mr-1 font-medium text-theme-sm">
          {currentUser?.name ?? "Utente"}
        </span>
        <ChevronDownIcon
          className={`stroke-gray-500 dark:stroke-gray-400 transition-transform duration-200 ${
            isOpen ? "rotate-180" : ""
          }`}
        />
      </button>

      <Dropdown
        isOpen={isOpen}
        onClose={closeDropdown}
        triggerId="user-dropdown"
        className="absolute right-0 mt-[17px] flex w-[280px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
      >
        <div>
          <span className="block font-medium text-gray-700 text-theme-sm dark:text-gray-400">
            {currentUser?.name ?? "Utente"}
          </span>
          <span className="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">
            {roleContext} · {currentUser?.email ?? ""}
          </span>
        </div>

        {logoutError ? (
          <div className="mt-3">
            <Alert variant="error" title="Disconnessione non riuscita" message={logoutError} />
          </div>
        ) : null}

        <div className="mt-3 border-y border-gray-100 py-3 dark:border-gray-800">
          <span
            id="theme-selection-label"
            className="mb-2 block px-3 text-theme-xs font-medium text-gray-500 dark:text-gray-400"
          >
            Tema
          </span>
          <div
            role="radiogroup"
            aria-labelledby="theme-selection-label"
            className="grid grid-cols-2 gap-2 px-3"
          >
            {([
              ["light", "Chiaro"],
              ["dark", "Scuro"],
            ] as const).map(([value, label]) => {
              const isSelected = theme === value;

              return (
                <label
                  key={value}
                  className={`cursor-pointer rounded-lg border px-3 py-2 text-center text-theme-sm font-medium transition-colors focus-within:ring-3 focus-within:ring-brand-500/20 ${
                    isSelected
                      ? "border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400 dark:bg-brand-500/15 dark:text-brand-300"
                      : "border-gray-200 text-gray-600 hover:bg-gray-100 hover:text-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                  }`}
                >
                  <input
                    type="radio"
                    name="theme"
                    value={value}
                    checked={isSelected}
                    onChange={() => selectTheme(value)}
                    className="sr-only"
                  />
                  {label}
                </label>
              );
            })}
          </div>
        </div>

        <DropdownItem
          onClick={() => void handleLogout()}
          baseClassName="flex w-full items-center gap-3 px-3 py-2 mt-3 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
        >
          <ArrowRightIcon className="h-6 w-6 fill-gray-500 group-hover:fill-gray-700 dark:fill-gray-400 dark:group-hover:fill-gray-300" />
          {isLoggingOut ? "Disconnessione…" : "Esci"}
        </DropdownItem>
      </Dropdown>
    </div>
  );
}
