#!/usr/bin/env node
/**
 * Invoiced Support MCP Server
 *
 * Enhanced MCP server for Invoiced with:
 * - Billing tools (read-only in production, full access in sandbox)
 * - Support-focused prompts (slash commands)
 * - Source code browsing tools
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { registerInvoicedTools } from '@invoiced/mcp-core';
import { registerPrompts } from './prompts.js';
import { registerSourceTools } from './source-tools.js';

const API_KEY = process.env.INVOICED_API_KEY;
const SANDBOX = process.env.INVOICED_SANDBOX === 'true';

if (!API_KEY) {
  console.error('Error: INVOICED_API_KEY environment variable is required');
  process.exit(1);
}

// Production mode = read-only (no create/update/delete operations)
const isProduction = !SANDBOX;

const server = new McpServer({
  name: 'invoiced-support',
  version: '1.0.0',
});

// Register Invoiced tools (read-only in production for safety)
const { registeredTools } = await registerInvoicedTools(server, API_KEY, SANDBOX, {
  readOnly: isProduction
});

if (isProduction) {
  console.error(`[invoiced-support] Production mode: ${registeredTools.length} read-only tools registered`);
} else {
  console.error(`[invoiced-support] Sandbox mode: ${registeredTools.length} tools registered (full access)`);
}

// Register support-specific prompts
registerPrompts(server);

// Register source code browsing tools
registerSourceTools(server);

// Connect via stdio
const transport = new StdioServerTransport();
await server.connect(transport);
