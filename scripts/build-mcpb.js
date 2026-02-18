#!/usr/bin/env node
/**
 * Build script for creating MCPB bundles
 *
 * Usage:
 *   node scripts/build-mcpb.js                    # Build all distributions
 *   node scripts/build-mcpb.js invoiced-mcp       # Build specific distribution
 *   node scripts/build-mcpb.js invoiced-support   # Build specific distribution
 */

import { execSync } from 'child_process';
import { existsSync, mkdirSync, rmSync, cpSync, readdirSync, statSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const rootDir = join(__dirname, '..');
const packagesDir = join(rootDir, 'packages');
const distDir = join(rootDir, 'dist');

// Available distributions
const DISTRIBUTIONS = ['invoiced-mcp', 'invoiced-support'];

// Files/folders to include in bundle
const INCLUDE_PATTERNS = [
  'manifest.json',
  'src',
  'docs',
  'README.md',
  'node_modules'
];

// Files/folders to exclude
const EXCLUDE_PATTERNS = [
  '*.test.js',
  '*.spec.js',
  '.git',
  '.DS_Store',
  'node_modules/.cache'
];

function log(msg) {
  console.log(`[build] ${msg}`);
}

function error(msg) {
  console.error(`[build] ERROR: ${msg}`);
  process.exit(1);
}

function buildDistribution(name) {
  const distPath = join(packagesDir, name);

  if (!existsSync(distPath)) {
    error(`Distribution not found: ${name}`);
  }

  log(`Building ${name}...`);

  // Create dist directory
  if (!existsSync(distDir)) {
    mkdirSync(distDir, { recursive: true });
  }

  // Create temp build directory
  const buildDir = join(distDir, `${name}-build`);
  if (existsSync(buildDir)) {
    rmSync(buildDir, { recursive: true });
  }
  mkdirSync(buildDir, { recursive: true });

  // Copy distribution files
  log(`  Copying distribution files...`);
  for (const pattern of INCLUDE_PATTERNS) {
    const srcPath = join(distPath, pattern);
    const destPath = join(buildDir, pattern);

    if (existsSync(srcPath)) {
      cpSync(srcPath, destPath, { recursive: true });
    }
  }

  // Copy core package into node_modules
  log(`  Bundling @invoiced/mcp-core...`);
  const coreDir = join(packagesDir, 'core');
  const coreDestDir = join(buildDir, 'node_modules', '@invoiced', 'mcp-core');

  mkdirSync(join(buildDir, 'node_modules', '@invoiced'), { recursive: true });
  cpSync(coreDir, coreDestDir, { recursive: true });

  // Install core dependencies into build
  log(`  Installing core dependencies...`);
  const coreDepsDir = join(coreDestDir, 'node_modules');
  if (!existsSync(coreDepsDir)) {
    mkdirSync(coreDepsDir, { recursive: true });
  }

  // Copy SDK and zod from root node_modules or install fresh
  const sdkSource = join(rootDir, 'node_modules', '@modelcontextprotocol');
  const zodSource = join(rootDir, 'node_modules', 'zod');

  if (existsSync(sdkSource)) {
    mkdirSync(join(buildDir, 'node_modules', '@modelcontextprotocol'), { recursive: true });
    cpSync(sdkSource, join(buildDir, 'node_modules', '@modelcontextprotocol'), { recursive: true });
  }

  if (existsSync(zodSource)) {
    cpSync(zodSource, join(buildDir, 'node_modules', 'zod'), { recursive: true });
  }

  // Copy any other dependencies from root node_modules
  const rootNodeModules = join(rootDir, 'node_modules');
  if (existsSync(rootNodeModules)) {
    const deps = readdirSync(rootNodeModules);
    for (const dep of deps) {
      if (dep.startsWith('.') || dep === '@invoiced' || dep === '@modelcontextprotocol' || dep === 'zod') {
        continue;
      }

      const srcDep = join(rootNodeModules, dep);
      const destDep = join(buildDir, 'node_modules', dep);

      if (!existsSync(destDep)) {
        cpSync(srcDep, destDep, { recursive: true });
      }
    }
  }

  // Create MCPB (zip file)
  const mcpbPath = join(distDir, `${name}.mcpb`);
  if (existsSync(mcpbPath)) {
    rmSync(mcpbPath);
  }

  log(`  Creating ${name}.mcpb...`);
  execSync(`cd "${buildDir}" && zip -rq "${mcpbPath}" . -x "*.git*" -x "*.DS_Store" -x "*node_modules/.cache/*"`, {
    stdio: 'ignore'
  });

  // Cleanup build directory
  rmSync(buildDir, { recursive: true });

  // Get file size
  const { size } = statSync(mcpbPath);
  const sizeMB = (size / 1024 / 1024).toFixed(2);

  log(`  Created: dist/${name}.mcpb (${sizeMB} MB)`);

  return mcpbPath;
}

// Main
const args = process.argv.slice(2);
const targetDist = args[0];

if (targetDist) {
  if (!DISTRIBUTIONS.includes(targetDist)) {
    error(`Unknown distribution: ${targetDist}. Available: ${DISTRIBUTIONS.join(', ')}`);
  }
  buildDistribution(targetDist);
} else {
  log('Building all distributions...');
  for (const dist of DISTRIBUTIONS) {
    buildDistribution(dist);
  }
}

log('Build complete!');
