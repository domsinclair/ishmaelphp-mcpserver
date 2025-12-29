# Security and Policies — Production Packaging

This page provides safeguards and CI patterns to prevent development tooling from leaking into production bundles. It complements the packer and environment-aware module filtering.

Environment flags

- APP_ENV — production | development | testing
- APP_DEBUG — true|false; do not rely on this to bypass safety in production
- ALLOW_DEV_MODULES — true|false; the only explicit override to include development modules when APP_ENV=production

Behavior summary

- Production: include shared and production modules; exclude development unless ALLOW_DEV_MODULES=true or `ish pack` is invoked with `--include-dev`.
- Development/Testing: include all modules by default.

Example .env snippets

```env
# Production (safe defaults)
APP_ENV=production
APP_DEBUG=false
ALLOW_DEV_MODULES=false

# Temporary staging override (be explicit and short‑lived)
# APP_ENV=production
# APP_DEBUG=true
# ALLOW_DEV_MODULES=true
```

CI guardrails (POSIX shell)

```sh
#!/usr/bin/env sh
set -euo pipefail

#
# Guard: fail build if development modules would leak into a production pack
#

php IshmaelPHP-Core/bin/ish modules:clear || true
php IshmaelPHP-Core/bin/ish route:clear || true
php IshmaelPHP-Core/bin/ish modules:cache --env=production

if jq -e '.[] | select(.env == "development")' storage/cache/modules.cache.json >/dev/null; then
  if [ "${ALLOW_DEV_MODULES:-false}" != "true" ]; then
    echo "Refusing to build: development modules detected for production and ALLOW_DEV_MODULES is not true" >&2
    exit 1
  fi
fi

php IshmaelPHP-Core/bin/ish pack --env=production --dry-run
```

CI guardrails (PowerShell)

```powershell
$ErrorActionPreference = 'Stop'

php IshmaelPHP-Core/bin/ish modules:clear | Out-Null
php IshmaelPHP-Core/bin/ish route:clear | Out-Null
php IshmaelPHP-Core/bin/ish modules:cache --env=production | Out-Null

$cache = Get-Content -Raw -Path "storage/cache/modules.cache.json" | ConvertFrom-Json
$hasDev = $false
foreach ($entry in $cache.PSObject.Properties.Value) {
  if ($entry.env -eq 'development') { $hasDev = $true; break }
}
if ($hasDev -and ($env:ALLOW_DEV_MODULES -ne 'true')) {
  Write-Error 'Refusing to build: development modules detected for production and ALLOW_DEV_MODULES is not true'
}

php IshmaelPHP-Core/bin/ish pack --env=production --dry-run | Write-Host
```

Policy checklist (copy‑pasteable)

```text
- [ ] APP_ENV=production and APP_DEBUG=false in CI runners
- [ ] Caches cleared before rebuild: ish modules:clear && ish route:clear
- [ ] Modules cache rebuilt for prod: ish modules:cache --env=production
- [ ] Build fails if development modules present and ALLOW_DEV_MODULES!=true
- [ ] --include-dev is not used unless approved and documented
- [ ] Dry‑run inspected; manifest.json archived as artifact
- [ ] No debug/test assets exported by module `export` lists
- [ ] For containers: caches precompiled in CI (immutable runtime)
```

References

- Packer CLI: ../cli-pack.md
- Module Types (security posture): modules/types.md
