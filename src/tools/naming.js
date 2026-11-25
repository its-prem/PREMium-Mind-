/**
 * Add a user-typed suffix at the end of a file name (before the extension).
 * "final" -> "Notes final"; "- Sem 3", "_v2", "(copy)" are used exactly as typed.
 */
export function withSuffix(name, suffix) {
  const s = suffix && suffix.trim() ? suffix.replace(/\s+$/, '') : '';
  if (!s) return name;
  return /^[\s\-_(\[.]/.test(s) ? name + s : `${name} ${s}`;
}
