import { useTheme } from "../../context/ThemeContext";

/** TailAdmin ThemeToggleButton component. */
export default function ThemeToggleButton() {
    const { theme, toggleTheme } = useTheme();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={theme === "dark" ? "Switch to light mode" : "Switch to dark mode"}
            className="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <span aria-hidden="true" className="text-xl leading-none dark:hidden">☾</span>
            <span aria-hidden="true" className="hidden text-xl leading-none dark:block">☼</span>
        </button>
    );
}
