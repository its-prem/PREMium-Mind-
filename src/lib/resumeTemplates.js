/**
 * Resume templates that are added by dropping a file into  src/templates/resume/
 *
 *   src/templates/resume/Sunrise.jsx   ->  the template id is  "sunrise"  (the file name, in small letters)
 *
 * The admin then makes a product (category: Resume Templates) and writes  template:sunrise  in its "Demo link" box.
 * Nothing else has to be registered: the files in that folder are found automatically.
 *
 * Why this is safer than a normal import: each template becomes its own small file that the browser downloads only
 * when somebody opens the editor with that template (see ResumeDocument.jsx). The home page, the store cards and
 * visitors who are not logged in never load it.
 */
import { lazy } from 'react';

const files = import.meta.glob('../templates/resume/*.jsx'); // lazy: { path: () => import(path) }

const loaders = {};
Object.entries(files).forEach(([path, load]) => {
  const id = path.split('/').pop().replace(/\.jsx$/i, '').toLowerCase();
  loaders[id] = load;
});

/** The built-in templates (always part of the site) */
export const BUILT_IN_TEMPLATES = ['modern', 'classic', 'compact'];

const components = {};

/** The React component of a dropped-in template, or null when there is none with that id. */
export function getCustomTemplate(id) {
  const key = String(id || '').toLowerCase();
  if (!loaders[key]) return null;
  if (!components[key]) components[key] = lazy(loaders[key]);
  return components[key];
}

export const isCustomTemplate = (id) => !!loaders[String(id || '').toLowerCase()];
export const isKnownTemplate = (id) => BUILT_IN_TEMPLATES.includes(String(id || '').toLowerCase()) || isCustomTemplate(id);
