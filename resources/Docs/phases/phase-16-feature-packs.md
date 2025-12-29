# Phase 16 — Feature Packs Kickoff

Status: Planned/Initiated (Dec 2025)

This phase starts the transition from bundled examples toward a slim core + composable Feature Packs.

Objectives:
1) Remove bundled Examples from the core repository and retire related CLI commands.
2) Provide a starter Uploads Feature Pack template aligned with Ishmael guidelines and Tailwind CSS.
3) Publish comprehensive guidance for creating Feature Packs.

## Milestones

### M1 — Decommission core Examples
- Remove `IshmaelPHP-Core/Examples` directory. ✓
- Remove CLI commands `examples:list` and `examples:install` from the core `ish` binary. ✓
- Update documentation and navigation to retire the examples installer page. ✓

Deliverables:
- Updated `bin/ish` without examples commands
- mkdocs navigation updated; examples installer page removed from nav (file may remain temporarily for historical reference)

### M2 — Uploads Feature Pack (template)
- Add a template Feature Pack under `Templates/FeaturePacks/Upload` containing:
  - `Modules/Upload/module.php` (manifest)
  - `Modules/Upload/routes.php` (routes)
  - `Modules/Upload/Controllers/UploadController.php` (basic upload handling)
  - `Modules/Upload/Views` (Tailwind-styled form and result pages)
  - `Modules/Upload/Config/upload.php` (limits and allowed extensions)
  - `composer.json` (sample pack manifest demonstrating recommended Composer metadata and extra.ishmael-module)
- Ensure the template uses only current core helpers and follows module discovery conventions.

Deliverables:
- Upload feature pack template folder ready to be copied into new projects

### M3 — Documentation: Creating Feature Packs
- New docs section: `Feature Packs/` with:
  - Overview page
  - “Creating a Feature Pack” comprehensive guide (structure, composer, manifests, security, testing, publishing)
- Cross-link to the Uploads template.

Deliverables:
- `Documentation/feature-packs/index.md`
- `Documentation/feature-packs/creating.md`
- mkdocs navigation updated

## Execution plan
1. Remove Examples and CLI commands (M1).
2. Add Uploads Feature Pack template (M2).
3. Author Feature Packs documentation and update docs navigation (M3).
4. Announce deprecation path for any tutorials relying on examples; update references over time.

## Notes and next steps
- Future phases may introduce a dedicated `ish add <pack>` workflow that publishes pack assets from vendor into the app.
- Consider splitting media into two packs later: base file uploads and optional image transformations.
