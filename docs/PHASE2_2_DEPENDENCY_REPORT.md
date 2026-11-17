# Phase 2.2 — Dependency Installation Report

**Status: BLOCKED**

Every command in this stage was actually attempted. None were skipped or faked. All failed for the same root cause: this sandbox's egress proxy rejects connections to the package registries the project needs.

## 1. `composer validate`

```
$ composer validate --no-check-publish --no-check-all
./composer.json is valid
```
**Result: PASS.** Schema is well-formed; this check needs no network access.

## 2. `composer install`

```
$ composer install --no-interaction
No composer.lock file present. Updating dependencies to latest instead of installing from lock file.
Loading composer repositories with package information

In CurlDownloader.php line 394:
  curl error 56 while downloading https://repo.packagist.org/packages.json: CONNECT tunnel failed, response 403
```
**Result: BLOCKED.**
- Exact package/URL: `https://repo.packagist.org/packages.json` (the Packagist root package index — needed before resolving any of the 7 direct + dev dependencies in `composer.json`).
- Exact error: `curl error 56`, `CONNECT tunnel failed, response 403`.
- Root cause: the sandbox's agent-proxy explicitly rejects `CONNECT` to `repo.packagist.org:443` (confirmed again via `curl -sS "$HTTPS_PROXY/__agentproxy/status"`, which lists this exact host/port under `recentRelayFailures` with `kind: connect_rejected`). This is a policy decision external to the project, not a bug in `composer.json`.
- **No vendor/ directory exists.** No autoloader exists. This blocks every later stage that requires PHP to execute Laravel application code (stages 5–14).

## 3. `npm install`

```
$ npm install
npm error code ENOTFOUND
npm error network request to https://registry.npmjs.org/@tailwindcss%2fforms failed, reason: getaddrinfo ENOTFOUND registry.npmjs.org
```
**Result: BLOCKED.**
- Exact package/URL: `https://registry.npmjs.org/@tailwindcss%2fforms` (first dependency npm attempted to resolve from `package.json`'s `devDependencies`).
- Exact error: `ENOTFOUND`, DNS resolution failure for `registry.npmjs.org`.
- Root cause: `registry.npmjs.org` is nominally listed in the agent-proxy's `noProxy` allowlist (meant to bypass the proxy for direct access) but DNS resolution for it fails regardless — i.e. there is no direct egress path either. No `node_modules/` exists; **this blocks stage 12 (Frontend & UI build)** entirely — `vite build` cannot run without its plugin dependencies installed.

## 4. `npm audit`

```
$ npm audit
npm error code ENOLOCK
npm error audit This command requires an existing lockfile.
```
**Result: BLOCKED** — no `package-lock.json` exists because `npm install` never completed (step 3). `npm audit --package-lock-only` was also attempted and fails identically (it still needs to resolve the dependency tree against the registry first).

## 5. `composer check-platform-reqs`

```
$ composer check-platform-reqs
No vendor dir present, checking platform requirements from the lock file
In Locker.php line 338: [LogicException] No lockfile found. Unable to read locked packages
```
**Result: BLOCKED** — no `composer.lock` exists (step 2 never completed). A manual cross-check of `composer.json`'s declared requirements against `php -m` (done by hand in `docs/PHASE2_2_ENVIRONMENT_REPORT.md`) stands in for this and surfaced **BUG-007** (missing `ext-bcmath`, P0 — see Environment Report and Bug Fix Log).

## 6. Manual dependency review (static, no execution)

Reviewed `composer.json` and `package.json` by hand since the automated checks above could not run:

| Package | Declared | Note |
|---|---|---|
| `laravel/framework` | `^12.0` | Matches installed PHP 8.3.6; target stack requirement satisfied on paper. |
| `laravel/sanctum` | `^4.0` | Standard for Laravel 12; no conflict visible. |
| `livewire/livewire` | `^3.5` | Matches "Livewire 3" requirement. |
| `spatie/laravel-permission` | `^6.9` | Compatible with Laravel 12 per its own published compatibility table (as of this model's training data — **not independently re-verified via network**, flagged as such). |
| `guzzlehttp/guzzle` | `^7.9` | Used by the 4 supplier adapters' HTTP client; no conflict visible. |
| `pestphp/pest` + `pest-plugin-laravel` | `^3.5` / `^3.0` | Matches "PHPUnit/Pest" requirement. |
| `@tailwindcss/forms`, `tailwindcss`, `vite`, `laravel-vite-plugin`, `alpinejs` | various | Matches "Tailwind + Vite + Alpine" requirement; no version conflicts visible between them on paper. |

No conflicting version constraints were found by manual inspection. This is **not** a substitute for an actual `composer install` dependency-resolution pass and is reported as **NOT VERIFIED**, not PASS.

## Summary

| Check | Status |
|---|---|
| `composer validate` | PASS |
| `composer install` | BLOCKED (network) |
| `npm install` | BLOCKED (network) |
| `npm audit` | BLOCKED (no lockfile, consequence of above) |
| `composer check-platform-reqs` | BLOCKED (no lockfile, consequence of above) — manual substitute found BUG-007 |
| Manual version-conflict review | NOT VERIFIED (static only) |
