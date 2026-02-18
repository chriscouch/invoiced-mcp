# Invoiced MCP Server

A [Model Context Protocol (MCP)](https://modelcontextprotocol.io) server for [Invoiced](https://invoiced.com) — a comprehensive billing and accounts receivable platform.

This MCP server provides Claude and other MCP-compatible AI assistants with the ability to interact with Invoiced's billing APIs, enabling operations like managing customers, creating invoices, processing payments, handling subscriptions, and more.

## Features

- **160+ Billing Tools** — Complete coverage of the Invoiced API
- **Support Workflows** — 6 built-in prompts for common support scenarios (diagnose payments, collection strategy, customer health, AR aging, invoice troubleshooting, subscription management)
- **Read-Only Production Mode** — Automatically restricts to read-only operations when connected to a production environment (sandbox gets full access)
- **Source Code Browsing** — Tools for browsing and searching Invoiced source code repositories

### Supported Operations

| Category | Operations |
|----------|------------|
| **Customers** | List, create, get, update, delete, balance, statements |
| **Invoices** | List, create, get, update, delete, send, void, pay, payment plans |
| **Payments** | List, create, get, update, delete, receipts, charges, refunds |
| **Estimates** | List, create, get, update, delete, send, void, convert to invoice |
| **Credit Notes** | List, create, get, update, delete, send, void |
| **Subscriptions** | List, create, get, update, cancel, preview |
| **Plans** | List, create, get, update, delete |
| **Catalog** | Items, tax rates, coupons, payment terms, late fees |
| **Tasks** | Create, list, update, delete tasks |
| **Chasing** | Chasing cadences for automated collections |
| **Reporting** | Reports, exports, events |
| **Admin** | Company settings, members, roles, webhooks, templates |

### Built-in Prompts

| Prompt | Description |
|--------|-------------|
| `diagnose-payment` | Diagnose payment issues for an invoice |
| `collection-strategy` | Create collection strategies for overdue invoices |
| `customer-health` | Comprehensive health check for a customer account |
| `ar-aging` | Generate and analyze accounts receivable aging reports |
| `troubleshoot-invoice` | Troubleshoot invoice issues (sending, payment, display, calculation) |
| `manage-subscription` | Help with subscription management (upgrade, downgrade, cancel, pause) |

## Installation

Clone and install locally:

```bash
git clone https://github.com/chriscouch/invoiced-mcp.git
cd invoiced-mcp
npm install
```

## Configuration

### Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `INVOICED_API_KEY` | Your Invoiced API key | Yes |
| `INVOICED_SANDBOX` | Set to `true` for sandbox environment (full read/write access) | No |

> **Note:** When `INVOICED_SANDBOX` is not set or `false`, the server runs in **read-only mode** — only list/get operations are available. Set `INVOICED_SANDBOX=true` to enable create, update, and delete operations.

### Getting an API Key

1. Log in to your [Invoiced dashboard](https://invoiced.com)
2. Navigate to **Settings > Developers > API Keys**
3. Create a new API key
4. Store it securely (it will only be shown once)

## Usage

### With Claude Desktop

Add to your Claude Desktop configuration (`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS):

```json
{
  "mcpServers": {
    "invoiced": {
      "command": "node",
      "args": ["packages/invoiced-support/src/index.js"],
      "cwd": "/path/to/invoiced-mcp",
      "env": {
        "INVOICED_API_KEY": "your_api_key_here",
        "INVOICED_SANDBOX": "true"
      }
    }
  }
}
```

### Running Locally

```bash
# Set environment variables
export INVOICED_API_KEY="your_api_key_here"
export INVOICED_SANDBOX="true"  # Optional, for sandbox (full access)

# Run the server
npm start --workspace=packages/invoiced-support
```

## Examples

### Create a Customer and Invoice

```
User: Create a new customer called "Acme Corp" with email billing@acme.com
      and NET 30 payment terms, then create an invoice for $1000 of
      consulting services.

Claude: [Uses create_customer and create_invoice tools]
```

### Diagnose a Payment Issue

```
User: /diagnose-payment

Claude: [Runs the diagnose-payment prompt to check invoice details,
        payment history, customer payment sources, and identify root causes]
```

### Generate Aging Report

```
User: /ar-aging

Claude: [Generates an aging report with total AR outstanding,
        aging buckets, DSO, and recommendations]
```

## API Reference

This MCP server wraps the [Invoiced REST API](https://developer.invoiced.com/api). For detailed field descriptions and API behavior, refer to the official documentation.

### Base URLs

- **Production**: `https://api.invoiced.com`
- **Sandbox**: `https://api.sandbox.invoiced.com`

### Rate Limiting

The Invoiced API has rate limits. If you exceed them, you'll receive HTTP 429 responses. The MCP server will return these errors to the client for handling.

## Security

- Store API keys securely using environment variables or secrets management
- Use the sandbox environment for testing
- Production mode is read-only by default to prevent accidental modifications

## Development

```bash
# Install dependencies
npm install

# Run tests
npm test

# Build .mcpb bundle
npm run build
```

## License

MIT

## Support

- [Invoiced Documentation](https://docs.invoiced.com)
- [API Reference](https://developer.invoiced.com/api)
- [Support](mailto:support@invoiced.com)
