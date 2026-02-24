# Ishmael MCP Server

Standalone Ishmael MCP (Model Context Protocol) server for deep integration with IshmaelPHP projects.

## Overview

The Ishmael MCP Server provides a standardised interface for AI models to interact with IshmaelPHP applications. It exposes tools for project analysis, feature pack management, testing, and more.

## Installation

Install via Composer:

```bash
composer require ishmael/mcp-server
```

## Usage

Run the server via the provided binary:

```bash
./vendor/bin/ish-mcp
```

### Transport

The server uses **JSON-RPC 2.0** over **Standard Input/Output (Stdio)**.

### Tools

- `health/version`: Get server version and health status.
- `project/info`: Get metadata about the detected Ishmael project.
- `feature-pack/list`: List available and installed feature packs.
- `feature-pack/create`: Scaffold a new feature pack.
- `test/run`: Execute project tests.
- `lint/run`: Run static analysis and linting.
- `log/tail`: Stream application logs.
- `migrate`: Run database migrations.

### Environment Variables

- `ISH_MCP_DEBUG`: Set to `1` to enable verbose error reporting.
- `ISH_MCP_INSECURE_TLS`: Set to `1` to disable SSL certificate verification for registry requests (dev only).
- `ISH_MCP_NO_BROWSER`: Set to `1` to prevent the server from automatically opening the browser for vendor registration/auth.
- `MCP_RATE_LIMIT`: Configure request rate limiting (default: 100/min).
- `MCP_REQUEST_TIMEOUT_MS`: Set maximum request duration (default: 30000ms).

## Development

### Running Tests

```bash
composer test
```

### Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

MIT License. See [LICENSE](LICENSE) for more information.
