/** Logo v `public/travian-stats-logo.png` (transparentné pozadie). */
export function brandLogoSrc(): string {
  const base = import.meta.env.BASE_URL
  return `${base.endsWith('/') ? base : `${base}/`}travian-stats-logo.png`
}
