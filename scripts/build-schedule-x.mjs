// One-shot vendor build: bundles Schedule-X (+ plugins + its theme CSS) into a
// single IIFE. Re-run only when bumping versions:
//
//   npm install && npm run build:vendor
//
// The output file (js/vendor/schedule-x/schedule-x.bundle.js) is committed so a
// normal checkout needs no build. The Schedule-X stylesheet is inlined into the
// bundle and injected at runtime — there is no separate vendor CSS to deploy.

import { build } from 'esbuild'
import { mkdirSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const jsOut = resolve(root, 'js/vendor/schedule-x/schedule-x.bundle.js')

mkdirSync(dirname(jsOut), { recursive: true })

const version = JSON.parse(
  readFileSync(resolve(root, 'node_modules/@schedule-x/calendar/package.json'), 'utf8')
).version

await build({
  entryPoints: [resolve(root, 'scripts/schedule-x-entry.js')],
  bundle: true,
  format: 'iife',
  target: ['es2019'],
  minify: true,
  legalComments: 'none',
  loader: { '.css': 'text' },
  outfile: jsOut,
  banner: { js: `/* Schedule-X ${version} — bundled for mj-member (do not edit; run npm run build:vendor) */` },
})

console.log(`✓ schedule-x ${version} (JS + theme CSS inlined)`)
console.log(`  → ${jsOut}`)
