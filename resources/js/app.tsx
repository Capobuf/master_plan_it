import { createInertiaApp } from "@inertiajs/react";
import type { ComponentType } from "react";
import { createRoot } from "react-dom/client";
import type { SharedPageProps } from "./types";

const applicationName = import.meta.env.VITE_APP_NAME ?? "Master Plan IT";
const pages = import.meta.glob<ComponentType<any>>("./pages/**/*.tsx", {
    import: "default",
});

void createInertiaApp<SharedPageProps>({
    title: (title) =>
        title ? `${title} — ${applicationName}` : applicationName,
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page();
    },
    setup({ el, App, props }) {
        if (!el) {
            throw new Error("Inertia application root was not found.");
        }

        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: "#344054",
    },
});
