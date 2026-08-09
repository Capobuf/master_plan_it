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
  label?: string;
  placeholder?: string;
  disabled?: boolean;
};

export default function DatePicker({
  id,
  mode,
  onChange,
  label,
  defaultDate,
  placeholder,
  disabled = false,
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
      static: true,
      monthSelectorType: "static",
      dateFormat: "Y-m-d",
      altInput: true,
      altFormat: "d/m/Y",
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
  }, [mode]);

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
  }, [id, label, placeholder]);

  return (
    <div>
      {label && <Label htmlFor={`${id}-display`}>{label}</Label>}

      <div className="relative">
        <input
          ref={inputRef}
          id={id}
          type="hidden"
          aria-hidden="true"
          placeholder={placeholder}
          disabled={disabled}
          className="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-12 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:disabled:bg-gray-800 dark:focus:border-brand-800"
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
    </div>
  );
}
