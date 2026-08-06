import { useTheme } from "../../context/ThemeContext";

/** TailAdmin ThemeTogglerTwo component for the authentication layout. */
export default function ThemeTogglerTwo() {
    const { theme, toggleTheme } = useTheme();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={theme === "dark" ? "Switch to light mode" : "Switch to dark mode"}
            className="inline-flex size-14 items-center justify-center rounded-full bg-brand-500 text-white transition-colors hover:bg-brand-600"
        >
            <span aria-hidden="true" className="text-2xl">{theme === "dark" ? "☼" : "☾"}</span>
        </button>
    );
}
