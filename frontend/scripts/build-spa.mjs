import { copyFileSync, existsSync, mkdirSync, readdirSync, rmSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

/*
 * Copies the built SPA next to Laravel's front controller so one deployment
 * serves both the API and the Vue app from the same origin — no CORS, one
 * domain, one package on Wasmer Edge.
 */
const here = dirname(fileURLToPath(import.meta.url))
const dist = resolve(here, '..', 'dist')
const target = resolve(here, '..', '..', 'backend', 'public')

if (!existsSync(dist)) {
  console.error('dist/ غير موجود — نفّذ npm run build أولاً.')
  process.exit(1)
}

// fs.cpSync() crashes Node on Windows when the project lives under a path with
// non-ASCII characters, so the walk is done by hand.
function copyTree(src, dest) {
  mkdirSync(dest, { recursive: true })

  for (const entry of readdirSync(src, { withFileTypes: true })) {
    if (entry.isDirectory()) copyTree(resolve(src, entry.name), resolve(dest, entry.name))
    else copyFileSync(resolve(src, entry.name), resolve(dest, entry.name))
  }
}

// Hashed asset names change on every build, so stale copies would pile up
// inside the API forever.
rmSync(resolve(target, 'assets'), { recursive: true, force: true })

// Copy the contents (not the folder itself) so index.php and .htaccess, which
// belong to Laravel, are left alone.
for (const entry of readdirSync(dist, { withFileTypes: true })) {
  const src = resolve(dist, entry.name)
  const dest = resolve(target, entry.name)

  if (entry.isDirectory()) copyTree(src, dest)
  else copyFileSync(src, dest)
}

console.log(`SPA copied to ${target}`)
