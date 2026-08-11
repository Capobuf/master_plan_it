import { useState, type FormEvent } from "react";
import { Navigate, useLocation, useNavigate } from "react-router";
import { EyeCloseIcon, EyeIcon } from "../../icons";
import { ApiError } from "../../api/client";
import { useAuth } from "../../context/AuthContext";
import Label from "../form/Label";
import Input from "../form/input/InputField";
import Alert from "../ui/alert/Alert";
import Button from "../ui/button/Button";
import { routes } from "../../navigation/routes";

interface FieldErrors {
  email?: string;
  password?: string;
}

interface FormError {
  message: string;
  correlationId: string | null;
}

function firstFieldMessage(value: unknown): string | undefined {
  if (typeof value === "string" && value.length > 0) {
    return value;
  }

  if (Array.isArray(value)) {
    return value.find(
      (message): message is string =>
        typeof message === "string" && message.length > 0,
    );
  }

  return undefined;
}

export default function SignInForm() {
  const { authenticated, loading, login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [showPassword, setShowPassword] = useState(false);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState<FormError | null>(null);

  if (loading) {
    return (
      <div
        className="flex flex-1 items-center justify-center"
        role="status"
        aria-live="polite"
      >
        <p className="text-sm text-gray-500 dark:text-gray-400">
          Caricamento della sessione…
        </p>
      </div>
    );
  }

  if (authenticated) {
    return <Navigate to={routes.panoramica} replace />;
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    try {
      await login({ email, password });
      const routeState = location.state as
        | { from?: { pathname?: string } }
        | null;
      navigate(routeState?.from?.pathname ?? routes.panoramica, { replace: true });
    } catch (error) {
      const apiError = ApiError.from(error);

      if (apiError.handledStatus === 422) {
        setFieldErrors({
          email: firstFieldMessage(apiError.fields.email)
            ? "Controlla l'indirizzo email."
            : undefined,
          password: firstFieldMessage(apiError.fields.password)
            ? "Controlla la password."
            : undefined,
        });
      }

      setFormError({
        message: apiError.message,
        correlationId:
          apiError.handledStatus === 500 || apiError.handledStatus === null
            ? apiError.correlationId
            : null,
      });
    } finally {
      setSubmitting(false);
    }
  };

  const errorMessage = formError?.correlationId
    ? `${formError.message} Riferimento tecnico: ${formError.correlationId}`
    : formError?.message;

  return (
    <div className="flex flex-col flex-1">
      <div className="flex flex-col justify-center flex-1 w-full max-w-md mx-auto">
        <div>
          <div className="mb-5 sm:mb-8">
            <h1 className="mb-2 font-semibold text-gray-800 text-title-sm dark:text-white/90 sm:text-title-md">
              Accedi
            </h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Inserisci email e password per accedere a Master Plan IT.
            </p>
          </div>
          <form onSubmit={handleSubmit}>
            <div className="space-y-6">
              {errorMessage ? (
                <Alert
                  variant="error"
                  title="Accesso non riuscito"
                  message={errorMessage}
                />
              ) : null}
              <div>
                <Label htmlFor="email">
                  Email <span className="text-error-500">*</span>
                </Label>
                <Input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  placeholder="name@example.com"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  disabled={submitting}
                  error={Boolean(fieldErrors.email)}
                  hint={fieldErrors.email}
                />
              </div>
              <div>
                <Label htmlFor="password">
                  Password <span className="text-error-500">*</span>
                </Label>
                <div className="relative">
                  <Input
                    id="password"
                    name="password"
                    type={showPassword ? "text" : "password"}
                    autoComplete="current-password"
                    placeholder="Inserisci la password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    disabled={submitting}
                    error={Boolean(fieldErrors.password)}
                    hint={fieldErrors.password}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((visible) => !visible)}
                    className="absolute z-30 -translate-y-1/2 cursor-pointer right-4 top-1/2"
                    aria-label={showPassword ? "Nascondi password" : "Mostra password"}
                    aria-pressed={showPassword}
                    disabled={submitting}
                  >
                    {showPassword ? (
                      <EyeIcon className="fill-gray-500 dark:fill-gray-400 size-5" />
                    ) : (
                      <EyeCloseIcon className="fill-gray-500 dark:fill-gray-400 size-5" />
                    )}
                  </button>
                </div>
              </div>
              <div>
                <Button className="w-full" size="sm" disabled={submitting}>
                  {submitting ? "Accesso…" : "Accedi"}
                </Button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
