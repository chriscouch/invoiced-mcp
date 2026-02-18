# Invoiced Support MCP Server

Enhanced MCP server for [Invoiced](https://invoiced.com) with support-focused workflows, guided prompts, and built-in documentation.

## Features

- **156 Billing Tools**: Full Invoiced API coverage
- **6 Guided Prompts**: Slash commands for common support tasks
- **Built-in Documentation**: Troubleshooting guides, error codes, best practices

## Available Prompts

| Command | Description |
|---------|-------------|
| `/diagnose-payment` | Diagnose payment issues for an invoice |
| `/collection-strategy` | Create prioritized collection strategy |
| `/customer-health` | Comprehensive customer account health check |
| `/ar-aging` | Analyze accounts receivable aging |
| `/troubleshoot-invoice` | Debug invoice problems |
| `/manage-subscription` | Subscription lifecycle management |

## Documentation Resources

The server includes searchable documentation:

- **API Reference** - Quick reference for all tools and fields
- **Troubleshooting Guide** - Common issues and solutions
- **Error Codes** - API error codes with resolutions
- **Workflows** - Step-by-step guides for common tasks
- **Best Practices** - Billing and collections recommendations

## Installation

### Claude Desktop

Add to your Claude Desktop configuration:

```json
{
  "mcpServers": {
    "invoiced-support": {
      "command": "npx",
      "args": ["@invoiced/mcp-support"],
      "env": {
        "INVOICED_API_KEY": "your_api_key_here",
        "INVOICED_SANDBOX": "true"
      }
    }
  }
}
```

### MCPB Installation

Double-click `invoiced-support.mcpb` or drag into Claude Desktop.

## Usage Examples

### Diagnose Payment Issue
```
/diagnose-payment invoice_id=INV-001234
```

Claude will:
1. Retrieve invoice details
2. Check payment history
3. Review customer payment sources
4. Identify issues and recommend solutions

### Create Collection Strategy
```
/collection-strategy min_days_overdue=30
```

Claude will:
1. Find all invoices 30+ days overdue
2. Categorize by aging bucket
3. Prioritize by amount
4. Create actionable collection plan

### Customer Health Check
```
/customer-health customer_id=12345
```

Claude will:
1. Review account details
2. Analyze invoice and payment history
3. Check subscription status
4. Provide health score and recommendations

## Configuration

| Variable | Description | Required |
|----------|-------------|----------|
| `INVOICED_API_KEY` | Your Invoiced API key | Yes |
| `INVOICED_SANDBOX` | Use sandbox environment | No |

## License

MIT
