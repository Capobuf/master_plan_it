import { useEffect, useRef } from "react";
import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.css";
import { Italian } from "flatpickr/dist/l10n/it.js";
import Label from "./Label";
import { CalenderIcon } from "../../icons";
import Hook = flatpickr.Options.Hook;
import DateOption = flatpickr.Options.DateOption;

type PropsType = {
  id: string;
  mode?: "single" | "multiple" | "range" | "time";
  onChange?: Hook | Hook[];
  defaultDate?: DateOption;
  minDate?: DateOption;
  maxDate?: DateOption;
  label?: string;
  hideLabel?: boolean;
  staticPosition?: boolean;
  placeholder?: string;
  disabled?: boolean;
  error?: boolean;
  hint?: string;
};

export default function DatePicker({
  id,
  mode,
  onChange,
  label,
  hideLabel = false,
  staticPosition = true,
  defaultDate,
  minDate,
  maxDate,
  placeholder,
  disabled = false,
  error = false,
  hint,
}: PropsType) {
  const inputRef = useRef<HTMLInputElement>(null);
  const pickerRef = useRef<flatpickr.Instance | null>(null);
  const onChangeRef = useRef(onChange);

  useEffect(() => {
    onChangeRef.current = onChange;
  }, [onChange]);

  useEffect(() => {
    if (!inputRef.current) return;

    const flatPickr = flatpickr(inputRef.current, {
      mode: mode || "single",
      static: staticPosition,
      monthSelectorType: "static",
      dateFormat: "Y-m-d",
      altInput: true,
      altFormat: "d/m/Y",
      minDate,
      maxDate,
      locale: Italian,
      disableMobile: true,
      onChange: (dates, currentDateString, instance, data) => {
        const callbacks = onChangeRef.current;
        if (!callbacks) return;
        (Array.isArray(callbacks) ? callbacks : [callbacks]).forEach((callback) => {
          callback(dates, currentDateString, instance, data);
        });
      },
    });

    if (!Array.isArray(flatPickr)) pickerRef.current = flatPickr;

    return () => {
      if (!Array.isArray(flatPickr)) {
        flatPickr.destroy();
      }
      pickerRef.current = null;
    };
  }, [maxDate, minDate, mode, staticPosition]);

  useEffect(() => {
    if (!pickerRef.current) return;
    if (defaultDate === undefined) pickerRef.current.clear(false);
    else pickerRef.current.setDate(defaultDate, false);
  }, [defaultDate]);

  useEffect(() => {
    const picker = pickerRef.current;
    if (!picker) return;
    picker.set("clickOpens", !disabled);
    picker.input.disabled = disabled;
    if (picker.altInput) picker.altInput.disabled = disabled;
    if (disabled) picker.close();
  }, [disabled]);

  useEffect(() => {
    const altInput = pickerRef.current?.altInput;
    if (!altInput) return;
    altInput.id = `${id}-display`;
    altInput.setAttribute("aria-label", label ?? placeholder ?? "Data");
    altInput.className = `${inputRef.current?.className ?? ""} form-control input`;
    if (error) altInput.setAttribute("aria-invalid", "true");
    else altInput.removeAttribute("aria-invalid");
    if (hint) altInput.setAttribute("aria-describedby", `${id}-hint`);
    else altInput.removeAttribute("aria-describedby");
  }, [error, hint, id, label, placeholder]);

  const inputClasses = `h-11 w-full appearance-none rounded-lg border bg-transparent px-4 py-2.5 pr-12 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:opacity-50 dark:bg-gray-900 dark:placeholder:text-white/30 dark:disabled:bg-gray-800 ${
    error
      ? "border-error-500 text-error-800 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500 dark:text-error-400 dark:focus:border-error-800"
      : "border-gray-300 text-gray-800 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
  }`;

  return (
    <div>
      {label && !hideLabel ? <Label htmlFor={`${id}-display`}>{label}</Label> : null}

      <div className="relative">
        <input
          ref={inputRef}
          id={id}
          type="hidden"
          aria-hidden="true"
          placeholder={placeholder}
          disabled={disabled}
          className={inputClasses}
        />

        <button
          type="button"
          className="absolute right-1 top-1/2 z-10 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-brand-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:pointer-events-none disabled:opacity-50 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-brand-400"
          aria-label={label ? `Apri calendario: ${label}` : "Apri calendario"}
          onClick={() => pickerRef.current?.open()}
          disabled={disabled}
        >
          <CalenderIcon className="size-6" />
        </button>
      </div>
      {hint ? <p id={`${id}-hint`} className={`mt-1.5 text-xs ${error ? "text-error-500" : "text-gray-500 dark:text-gray-400"}`}>{hint}</p> : null}
    </div>
  );
}
