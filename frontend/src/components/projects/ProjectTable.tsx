import { Link, useNavigate } from "react-router";
import type { Project } from "../../api/projects";
import { PencilIcon } from "../../icons";
import { routes } from "../../navigation/routes";
import { domainLabel } from "../../presentation/labels";
import IconButton from "../common/IconButton";
import Badge from "../ui/badge/Badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "../ui/table";

const stageColor = (stage: Project["stage"]): "success" | "warning" | "info" | "error" | "light" => {
  if (stage === "approved") return "success";
  if (stage === "proposed") return "info";
  if (stage === "idea") return "warning";
  if (stage === "rejected") return "error";
  return "light";
};

export default function ProjectTable({ projects, canEdit }: { projects: Project[]; canEdit: boolean }) {
  const navigate = useNavigate();
  return <>
    <div className="space-y-3 lg:hidden">
      {projects.map((project) => <article key={project.id} className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
        <div className="flex items-start justify-between gap-3">
          <Link to={routes.progetto(project.id)} className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">{project.title}</Link>
          {canEdit ? <IconButton icon={PencilIcon} label={`Modifica ${project.title}`} onClick={() => navigate(routes.modificaProgetto(project.id))} /> : null}
        </div>
        <div className="mt-3"><Badge color={stageColor(project.stage)} size="sm">{domainLabel(project.stage)}</Badge></div>
        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
          <div><dt className="text-gray-500 dark:text-gray-400">Centro di costo</dt><dd className="mt-1 text-gray-700 dark:text-gray-300">{project.cost_center?.name ?? "—"}</dd></div>
          <div><dt className="text-gray-500 dark:text-gray-400">Anno di destinazione</dt><dd className="mt-1 text-gray-700 dark:text-gray-300">{project.deferred_target_planning_year?.year_label ?? "—"}</dd></div>
          <div><dt className="text-gray-500 dark:text-gray-400">Spese</dt><dd className="mt-1 text-gray-700 dark:text-gray-300">{project.expense_count}</dd></div>
        </dl>
      </article>)}
    </div>
    <div className="hidden max-w-full overflow-x-auto lg:block"><Table>
      <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]"><TableRow>{["Titolo", "Stage", "Centro di costo", "Anno di destinazione", "Spese", "Azioni"].map((heading) => <TableCell key={heading} isHeader className="whitespace-nowrap px-4 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400">{heading}</TableCell>)}</TableRow></TableHeader>
      <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">{projects.map((project) => <TableRow key={project.id}>
        <TableCell className="min-w-56 px-4 py-3"><Link to={routes.progetto(project.id)} className="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">{project.title}</Link></TableCell>
        <TableCell className="px-4 py-3"><Badge color={stageColor(project.stage)} size="sm">{domainLabel(project.stage)}</Badge></TableCell>
        <TableCell className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{project.cost_center?.name ?? "—"}</TableCell>
        <TableCell className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{project.deferred_target_planning_year?.year_label ?? "—"}</TableCell>
        <TableCell className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{project.expense_count}</TableCell>
        <TableCell className="px-4 py-3">{canEdit ? <IconButton icon={PencilIcon} label={`Modifica ${project.title}`} onClick={() => navigate(routes.modificaProgetto(project.id))} /> : null}</TableCell>
      </TableRow>)}</TableBody>
    </Table></div>
  </>;
}
