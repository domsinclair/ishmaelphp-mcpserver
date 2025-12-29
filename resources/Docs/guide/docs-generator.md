# Docs Generator (MVP)

Ishmael ships with a minimal documentation generator available from the CLI.

Command

- ish docs:generate

What it does

- CLI Reference: Scans known CLI commands and generates a reference page with a summary table and per-command options.
- Modules Reference: Reads each module's module.json (if present) and combines it with discovered routes to produce per-module pages and an index.
- Core API Placeholder: Creates a placeholder page for API docs because external generators (Doctum/phpDocumentor) are not currently configured in this environment.

Outputs

- IshmaelPHP-Core/Documentation/reference/cli-commands.md
- IshmaelPHP-Core/Documentation/reference/modules/_index.md
- IshmaelPHP-Core/Documentation/reference/modules/<Module>.md
- IshmaelPHP-Core/Documentation/reference/core-api/_index.md (placeholder)

Notes

- The generator does not currently run Doctum or phpDocumentor. When those tools are available, they can populate the Core API section automatically.
- All generated pages adhere to the existing MkDocs structure and are linked in mkdocs.yml under Reference.
- The generator is idempotent and will overwrite the generated files each time it runs.

Example

php bin/ish docs:generate

You should see console output summarizing the generated sections and file paths.
