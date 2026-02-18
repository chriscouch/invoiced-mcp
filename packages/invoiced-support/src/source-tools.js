/**
 * Source code tools for Invoiced Support
 *
 * Provides tools for Claude to browse and read Invoiced source code.
 */

import { readFileSync, existsSync, readdirSync } from 'fs';
import { join, dirname, extname, relative } from 'path';
import { fileURLToPath } from 'url';
import { z } from 'zod';

const __dirname = dirname(fileURLToPath(import.meta.url));
const docsDir = join(__dirname, '..', 'docs');

// Source code repositories
const REPOS = {
  'admin-dev': 'Admin Portal (PHP/Symfony)',
  'backend-dev': 'Backend API',
  'frontend-dev': 'Frontend Application',
  'netsuite-master': 'NetSuite Integration'
};

// File extensions to expose (text-based files only)
const ALLOWED_EXTENSIONS = new Set([
  '.js', '.ts', '.jsx', '.tsx',
  '.php', '.twig',
  '.html', '.css', '.less', '.scss',
  '.json', '.yaml', '.yml', '.xml',
  '.md', '.txt',
  '.sh', '.bash',
  '.env', '.gitignore', '.dockerignore',
  '.sql'
]);

// Check if file should be exposed
function isAllowedFile(filename) {
  const ext = extname(filename).toLowerCase();
  if (!ext && (filename.startsWith('.') || ['Dockerfile', 'Makefile'].includes(filename))) {
    return true;
  }
  return ALLOWED_EXTENSIONS.has(ext);
}

// Recursively get all files in directory
function getAllFiles(dir, baseDir = dir) {
  const files = [];
  if (!existsSync(dir)) return files;

  const entries = readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = join(dir, entry.name);
    if (entry.name.startsWith('.') || entry.name === 'node_modules' || entry.name === 'vendor') {
      continue;
    }
    if (entry.isDirectory()) {
      files.push(...getAllFiles(fullPath, baseDir));
    } else if (isAllowedFile(entry.name)) {
      files.push(relative(baseDir, fullPath));
    }
  }
  return files;
}

// Match files against a glob-like pattern
function matchPattern(filepath, pattern) {
  if (!pattern) return true;

  // Convert glob to regex
  const regex = new RegExp(
    pattern
      .replace(/\./g, '\\.')
      .replace(/\*\*/g, '{{GLOBSTAR}}')
      .replace(/\*/g, '[^/]*')
      .replace(/{{GLOBSTAR}}/g, '.*'),
    'i'
  );
  return regex.test(filepath);
}

/**
 * Register source code tools with the MCP server
 */
export function registerSourceTools(server) {

  // Tool: List repositories
  server.registerTool(
    'list_source_repos',
    {
      description: 'List available Invoiced source code repositories',
      inputSchema: z.object({})
    },
    async () => {
      const repos = [];
      for (const [name, description] of Object.entries(REPOS)) {
        const repoDir = join(docsDir, name);
        if (existsSync(repoDir)) {
          const files = getAllFiles(repoDir);
          repos.push({ name, description, fileCount: files.length });
        }
      }
      return {
        content: [{
          type: 'text',
          text: JSON.stringify(repos, null, 2)
        }]
      };
    }
  );

  // Tool: List files in a repository
  server.registerTool(
    'list_source_files',
    {
      description: 'List source files in an Invoiced repository. Use pattern to filter (e.g., "*.php", "src/**/*.js", "Controller")',
      inputSchema: z.object({
        repo: z.enum(['admin-dev', 'backend-dev', 'frontend-dev', 'netsuite-master'])
          .describe('Repository name'),
        pattern: z.string().optional()
          .describe('Optional glob pattern to filter files (e.g., "*.php", "src/**/*.ts", "Controller")'),
        directory: z.string().optional()
          .describe('Optional subdirectory to list (e.g., "src/Controller")')
      })
    },
    async ({ repo, pattern, directory }) => {
      const repoDir = join(docsDir, repo);

      if (!existsSync(repoDir)) {
        return {
          content: [{ type: 'text', text: `Repository not found: ${repo}` }],
          isError: true
        };
      }

      const searchDir = directory ? join(repoDir, directory) : repoDir;
      if (!existsSync(searchDir)) {
        return {
          content: [{ type: 'text', text: `Directory not found: ${directory}` }],
          isError: true
        };
      }

      let files = getAllFiles(searchDir, repoDir);

      if (pattern) {
        files = files.filter(f => matchPattern(f, pattern));
      }

      return {
        content: [{
          type: 'text',
          text: JSON.stringify({
            repo,
            directory: directory || '/',
            pattern: pattern || null,
            fileCount: files.length,
            files: files.sort()
          }, null, 2)
        }]
      };
    }
  );

  // Tool: Read a source file
  server.registerTool(
    'read_source_file',
    {
      description: 'Read the contents of a source file from an Invoiced repository',
      inputSchema: z.object({
        repo: z.enum(['admin-dev', 'backend-dev', 'frontend-dev', 'netsuite-master'])
          .describe('Repository name'),
        path: z.string()
          .describe('File path relative to repository root (e.g., "src/Controller/InvoiceController.php")')
      })
    },
    async ({ repo, path }) => {
      const repoDir = join(docsDir, repo);
      const filepath = join(repoDir, path);

      if (!existsSync(repoDir)) {
        return {
          content: [{ type: 'text', text: `Repository not found: ${repo}` }],
          isError: true
        };
      }

      if (!existsSync(filepath)) {
        return {
          content: [{ type: 'text', text: `File not found: ${path}` }],
          isError: true
        };
      }

      // Security: ensure path doesn't escape repo directory
      const normalizedPath = join(repoDir, path);
      if (!normalizedPath.startsWith(repoDir)) {
        return {
          content: [{ type: 'text', text: 'Invalid path' }],
          isError: true
        };
      }

      try {
        const content = readFileSync(filepath, 'utf-8');
        return {
          content: [{
            type: 'text',
            text: `// File: ${repo}/${path}\n\n${content}`
          }]
        };
      } catch (err) {
        return {
          content: [{ type: 'text', text: `Error reading file: ${err.message}` }],
          isError: true
        };
      }
    }
  );

  // Tool: Search source files
  server.registerTool(
    'search_source_files',
    {
      description: 'Search for text/code patterns across Invoiced source files',
      inputSchema: z.object({
        query: z.string()
          .describe('Search query (text or regex pattern)'),
        repo: z.enum(['admin-dev', 'backend-dev', 'frontend-dev', 'netsuite-master']).optional()
          .describe('Optional: limit search to specific repository'),
        pattern: z.string().optional()
          .describe('Optional: limit search to files matching pattern (e.g., "*.php")'),
        maxResults: z.number().optional().default(50)
          .describe('Maximum number of results to return (default: 50)')
      })
    },
    async ({ query, repo, pattern, maxResults = 50 }) => {
      const results = [];
      const reposToSearch = repo ? [repo] : Object.keys(REPOS);

      let regex;
      try {
        regex = new RegExp(query, 'gi');
      } catch {
        regex = new RegExp(query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
      }

      for (const repoName of reposToSearch) {
        const repoDir = join(docsDir, repoName);
        if (!existsSync(repoDir)) continue;

        const files = getAllFiles(repoDir);

        for (const file of files) {
          if (results.length >= maxResults) break;
          if (pattern && !matchPattern(file, pattern)) continue;

          const filepath = join(repoDir, file);
          try {
            const content = readFileSync(filepath, 'utf-8');
            const lines = content.split('\n');

            for (let i = 0; i < lines.length; i++) {
              if (results.length >= maxResults) break;
              if (regex.test(lines[i])) {
                results.push({
                  repo: repoName,
                  file,
                  line: i + 1,
                  content: lines[i].trim().substring(0, 200)
                });
                regex.lastIndex = 0; // Reset regex state
              }
            }
          } catch {
            // Skip files that can't be read
          }
        }
      }

      return {
        content: [{
          type: 'text',
          text: JSON.stringify({
            query,
            repo: repo || 'all',
            pattern: pattern || null,
            resultCount: results.length,
            results
          }, null, 2)
        }]
      };
    }
  );
}
