import { createContext, useContext, useEffect, useState, type ReactNode } from "react";

type Theme = "light" | "dark";

type ThemeContextValue = {
    theme: Theme;
    toggleTheme: () => void;
};

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

/** TailAdmin ThemeContext pattern with persisted light/dark preference. */
export function ThemeProvider({ children }: { children: ReactNode }) {
    const [theme, setTheme] = useState<Theme>("light");
    const [initialized, setInitialized] = useState(false);

    useEffect(() => {
        const savedTheme = window.localStorage.getItem("theme");
        setTheme(savedTheme === "dark" ? "dark" : "light");
        setInitialized(true);
    }, []);

    useEffect(() => {
        if (!initialized) return;
        window.localStorage.setItem("theme", theme);
        document.documentElement.classList.toggle("dark", theme === "dark");
    }, [initialized, theme]);

    return (
        <ThemeContext.Provider
            value={{
                theme,
                toggleTheme: () => setTheme((current) => (current === "light" ? "dark" : "light")),
            }}
        >
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (!context) throw new Error("useTheme must be used within a ThemeProvider");
    return context;
}
