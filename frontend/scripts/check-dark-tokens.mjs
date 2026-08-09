import { readFileSync, readdirSync, statSync } from "node:fs";
import { extname, join, relative } from "node:path";

const root = new URL("../", import.meta.url);
const sourceRoot = new URL("src/", root);
const stylesheet = readFileSync(new URL("src/index.css", root), "utf8");
const requiredRules = [
  [/body\s*\{[\s\S]*?dark:text-gray-100;/, "body deve propagare il token testo dark"],
  [/html\.dark\s*\{\s*color-scheme:\s*dark;/, "html.dark deve attivare color-scheme dark"],
  [/input,[\s\S]*?button\s*\{\s*color:\s*inherit;/, "i controlli nativi devono ereditare il colore"],
  [/option\s*\{[\s\S]*?dark:text-gray-100;/, "le option native devono avere token dark"],
];

const failures = requiredRules
  .filter(([pattern]) => !pattern.test(stylesheet))
  .map(([, message]) => `index.css: ${message}`);

function files(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    return entry.isDirectory() ? files(path) : [path];
  });
}

for (const path of files(sourceRoot.pathname)) {
  if (![".css", ".tsx", ".ts"].includes(extname(path)) || !statSync(path).isFile()) continue;
  const content = readFileSync(path, "utf8");
  const file = relative(sourceRoot.pathname, path);
  if (/\btext-black\b/.test(content) || /color\s*:\s*black\b/i.test(content)) {
    failures.push(`${file}: colore nero assoluto non compatibile con dark mode`);
  }
  for (const match of content.matchAll(/className=(?:"([^"]*)"|`([^`]*)`)/g)) {
    const classes = match[1] ?? match[2] ?? "";
    if (/(?:^|\s)text-gray-(?:700|800|900|950)\b/.test(classes) && !/\bdark:text-/.test(classes)) {
      const line = content.slice(0, match.index).split("\n").length;
      failures.push(`${file}:${line}: testo scuro esplicito senza variante dark`);
    }
  }
}

if (failures.length > 0) {
  console.error(`Dark token check failed (${failures.length}):\n${failures.map((failure) => `- ${failure}`).join("\n")}`);
  process.exit(1);
}

console.log("Dark token propagation verified across frontend sources.");
