import { Link } from "@inertiajs/react";
import type { BreadcrumbItem } from "../../types";

type PageBreadcrumbProps = {
    title: string;
    crumbs?: BreadcrumbItem[];
};

/** TailAdmin React PageBreadcrumb component, using Inertia links for this application. */
export default function PageBreadcrumb({ title, crumbs = [] }: PageBreadcrumbProps) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{title}</h2>
            <nav aria-label="Breadcrumb">
                <ol className="flex items-center gap-1.5">
                    <li>
                        <Link className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-500 dark:text-gray-400" href="/">
                            Home <span aria-hidden="true">›</span>
                        </Link>
                    </li>
                    {crumbs.filter((crumb) => crumb.label !== title).map((crumb) => (
                        <li key={crumb.label} className="text-sm text-gray-800 dark:text-white/90">
                            {crumb.href ? <Link className="text-gray-500 hover:text-brand-500 dark:text-gray-400" href={crumb.href}>{crumb.label}</Link> : crumb.label}
                        </li>
                    ))}
                    <li className="text-sm text-gray-800 dark:text-white/90">{title}</li>
                </ol>
            </nav>
        </div>
    );
}
