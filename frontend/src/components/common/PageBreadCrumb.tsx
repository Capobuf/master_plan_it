import type { ReactNode } from "react";
import { Link } from "react-router";
import { routes } from "../../navigation/routes";

interface BreadcrumbProps {
  pageTitle: string;
  subtitle?: string;
  actions?: ReactNode;
}

const PageBreadcrumb: React.FC<BreadcrumbProps> = ({ pageTitle, subtitle, actions }) => {
  return (
    <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <nav aria-label="Percorso di navigazione">
          <ol className="mb-2 flex items-center gap-1.5">
            {pageTitle !== "Panoramica" ? <li>
            <Link
              className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
              to={routes.panoramica}
            >
              Panoramica
              <svg
                className="stroke-current"
                width="17"
                height="16"
                viewBox="0 0 17 16"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                  stroke=""
                  strokeWidth="1.2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            </Link>
            </li> : null}
          {pageTitle !== "Panoramica" ? <li className="text-sm text-gray-800 dark:text-white/90">
            {pageTitle}
          </li> : null}
        </ol>
      </nav>
        <h1 className="text-2xl font-semibold text-gray-800 dark:text-white/90">{pageTitle}</h1>
        {subtitle ? <p className="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{subtitle}</p> : null}
      </div>
      {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
    </header>
  );
};

export default PageBreadcrumb;
