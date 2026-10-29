import { readFile, writeFile } from "node:fs/promises";

// No bundler: concatenate the independent native Panel component sources.
for (const [output, sources] of Object.entries({
  "index.js": ["assets/fields.js", "assets/dashboard.js", "assets/global-settings.js"],
  "index.css": ["assets/fields.css", "assets/dashboard.css", "assets/global-settings.css"],
})) {
  await writeFile(new URL(`../${output}`, import.meta.url), (await Promise.all(sources.map(source => readFile(new URL(`../${source}`, import.meta.url), "utf8")))).join("\n"));
}
