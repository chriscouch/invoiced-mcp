/**
 * @invoiced/mcp-core
 *
 * Core MCP tools and handlers for Invoiced.
 * Shared between all Invoiced MCP distributions.
 */

export { InvoicedClient } from './src/invoiced-client.js';
export { allTools } from './src/tools/index.js';
export { handleToolCall } from './src/handlers/index.js';

// Read-only tool prefixes (safe for production)
const READ_ONLY_PREFIXES = [
  'get_',
  'list_',
  'generate_',
  'preview_',
  'email_autocomplete'
];

/**
 * Check if a tool is read-only (safe for production)
 */
export function isReadOnlyTool(toolName) {
  return READ_ONLY_PREFIXES.some(prefix => toolName.startsWith(prefix));
}

/**
 * Register Invoiced tools with an MCP server
 *
 * @param {McpServer} server - The MCP server instance
 * @param {string} apiKey - Invoiced API key
 * @param {boolean} sandbox - Whether to use sandbox environment
 * @param {object} options - Additional options
 * @param {boolean} options.readOnly - Only register read-only tools (for production safety)
 * @returns {{ server: McpServer, client: InvoicedClient, registeredTools: string[] }}
 */
export async function registerInvoicedTools(server, apiKey, sandbox = false, options = {}) {
  const { InvoicedClient } = await import('./src/invoiced-client.js');
  const { allTools } = await import('./src/tools/index.js');
  const { handleToolCall } = await import('./src/handlers/index.js');

  const { readOnly = false } = options;
  const client = new InvoicedClient(apiKey, sandbox);
  const registeredTools = [];

  // Filter tools if read-only mode
  const toolsToRegister = readOnly
    ? allTools.filter(tool => isReadOnlyTool(tool.name))
    : allTools;

  // Register tools - SDK accepts zod schemas directly
  for (const tool of toolsToRegister) {
    server.registerTool(
      tool.name,
      {
        description: tool.description,
        inputSchema: tool.inputSchema,
      },
      async (args) => {
        try {
          return await handleToolCall(tool.name, client, args || {});
        } catch (error) {
          return {
            content: [{
              type: 'text',
              text: JSON.stringify({ error: error.message, tool: tool.name })
            }],
            isError: true
          };
        }
      }
    );
    registeredTools.push(tool.name);
  }

  return { server, client, registeredTools };
}
