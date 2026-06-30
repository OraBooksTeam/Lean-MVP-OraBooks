#!/usr/bin/env node

/**
 * OraBooks Deploy Script
 *
 * Builds the UI, then deploys the assets to a managed WordPress host via SFTP/SSH.
 *
 * Usage:
 *   node scripts/deploy.mjs                          # uses .env.deploy
 *   node scripts/deploy.mjs --dry-run                # build only, no upload
 *   node scripts/deploy.mjs --env .env.prod          # custom env file
 *
 * Environment variables (see .env.deploy.example):
 *   DEPLOY_HOST       – SFTP hostname
 *   DEPLOY_PORT       – SFTP port (default 22)
 *   DEPLOY_USER       – SFTP username
 *   DEPLOY_KEY_PATH   – Path to SSH private key (default: ~/.ssh/id_rsa)
 *   DEPLOY_TARGET_DIR – Remote path to wp-content/plugins/OraBooks Lean MVP/
 *   BUILD_OUTPUT_DIR  – Local path to built assets (default: ../assets/react)
 */

import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createInterface } from 'node:readline';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const pluginRoot = path.resolve(root, '..');

// ---------------------------------------------------------------------------
// Parse args
// ---------------------------------------------------------------------------
const args = process.argv.slice(2);
const isCI = process.env.CI === 'true';
const dryRun = args.includes('--dry-run');
const envFileIndex = args.indexOf('--env');
const envFilePath = envFileIndex !== -1 ? args[envFileIndex + 1] : null;

// ---------------------------------------------------------------------------
// Load environment
// ---------------------------------------------------------------------------
function loadEnv(filePath) {
  const env = {};
  if (!fs.existsSync(filePath)) return env;
  const lines = fs.readFileSync(filePath, 'utf8').split('\n');
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eqIndex = trimmed.indexOf('=');
    if (eqIndex === -1) continue;
    const key = trimmed.slice(0, eqIndex).trim();
    const value = trimmed.slice(eqIndex + 1).trim().replace(/^['"]|['"]$/g, '');
    env[key] = value;
  }
  return env;
}

const envFile = envFilePath || path.join(root, '.env.deploy');
const env = loadEnv(envFile);
if (Object.keys(env).length === 0 && !dryRun) {
  console.log('No .env.deploy found. Running build-only (dry-run).');
}

const HOST = env.DEPLOY_HOST || process.env.DEPLOY_HOST;
const PORT = env.DEPLOY_PORT || process.env.DEPLOY_PORT || '22';
const USER = env.DEPLOY_USER || process.env.DEPLOY_USER;
const KEY_PATH = env.DEPLOY_KEY_PATH || process.env.DEPLOY_KEY_PATH || path.join(osHomedir(), '.ssh', 'id_rsa');
const TARGET_DIR = env.DEPLOY_TARGET_DIR || process.env.DEPLOY_TARGET_DIR;
const BUILD_OUTPUT = path.resolve(root, env.BUILD_OUTPUT_DIR || '../assets/react');

function osHomedir() {
  return process.env.HOME || process.env.USERPROFILE || '.';
}

// ---------------------------------------------------------------------------
// Check prerequisites
// ---------------------------------------------------------------------------
function checkCommand(cmd) {
  const result = spawnSync(process.platform === 'win32' ? 'where' : 'which', [cmd], {
    stdio: 'pipe',
    shell: process.platform === 'win32',
  });
  if (result.status !== 0) {
    console.warn(`Warning: '${cmd}' not found. Windows users: run from Git Bash or WSL.`);
    return false;
  }
  return true;
}

checkCommand('ssh');
checkCommand('tar');

// ---------------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------------
function run(label, command, args, options = {}) {
  console.log(`\n>> ${label}`);
  const result = spawnSync(command, args, {
    cwd: options.cwd || root,
    stdio: 'inherit',
    shell: process.platform === 'win32',
    env: { ...process.env, ...options.env },
  });
  if (result.status !== 0) {
    console.error(`${label} failed with exit ${result.status}`);
    if (result.error) console.error(result.error.message);
    return false;
  }
  return true;
}

function confirm(prompt) {
  const rl = createInterface({ input: process.stdin, output: process.stdout });
  return new Promise((resolve) => {
    rl.question(`${prompt} (y/N) `, (answer) => {
      rl.close();
      resolve(answer.trim().toLowerCase() === 'y');
    });
  });
}

// ---------------------------------------------------------------------------
// Steps
// ---------------------------------------------------------------------------
async function deploy() {
  console.log('╔══════════════════════════════════════════╗');
  console.log('║   OraBooks Production Deploy             ║');
  console.log('╚══════════════════════════════════════════╝');
  console.log(`  Dry run:      ${dryRun ? 'yes' : 'no'}`);
  console.log(`  Build output: ${BUILD_OUTPUT}`);
  console.log(`  Target host:  ${HOST || '(not configured)'}`);
  console.log(`  Target dir:   ${TARGET_DIR || '(not configured)'}`);
  console.log('');

  // ---- Step 1: Typecheck ----
  if (!run('Type check', 'npm', ['run', 'typecheck'])) {
    process.exit(1);
  }

  // ---- Step 2: Build ----
  if (!run('Build UI', 'npm', ['run', 'build'])) {
    console.error('Build failed. Aborting deploy.');
    process.exit(1);
  }

  // ---- Step 3: Verify manifest ----
  const manifestPath = path.join(BUILD_OUTPUT, 'deploy-manifest.json');
  if (!fs.existsSync(manifestPath)) {
    console.error('deploy-manifest.json not found. Build may have failed.');
    process.exit(1);
  }

  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  console.log(`\nBuild manifest: ${manifest.files.length} files ready for deploy.`);
  manifest.files.forEach((f) => console.log(`  ${f}`));

  // ---- Step 4: Prompt confirmation (skip in CI, or auto-proceed) ----
  if (!dryRun && HOST && USER && TARGET_DIR && !isCI) {
    const ok = await confirm(`\nDeploy ${manifest.files.length} files to ${USER}@${HOST}:${TARGET_DIR}?`);
    if (!ok) {
      console.log('Deploy cancelled.');
      process.exit(0);
    }
  }

  if (dryRun) {
    console.log('\nDry-run complete. Files would have been deployed:');
    manifest.files.forEach((f) => console.log(`  ${f}`));
    return;
  }

  if (!HOST || !USER || !TARGET_DIR) {
    console.log('\nDeploy target not configured. Build output is ready at:');
    console.log(`  ${BUILD_OUTPUT}`);
    console.log('Configure .env.deploy or run --dry-run to preview.');
    return;
  }

  // ---- Step 5: Transfer via tar-over-SSH ----
  console.log(`\n>> Transferring to ${USER}@${HOST}:${PORT}`);
  const tarResult = spawnSync('tar', ['czf', '-', ...manifest.files], {
    cwd: BUILD_OUTPUT,
    stdio: ['pipe', 'pipe', 'inherit'],
    shell: false,
  });

  if (tarResult.error || tarResult.status !== 0) {
    console.error('Failed to create tar archive.');
    process.exit(1);
  }

  const sshArgs = [
    '-o', 'StrictHostKeyChecking=accept-new',
    '-o', 'UserKnownHostsFile=/dev/null',
    '-p', PORT,
    '-i', KEY_PATH,
    `${USER}@${HOST}`,
    `mkdir -p '${TARGET_DIR}assets/react' && tar xzf - -C '${TARGET_DIR}assets/react'`,
  ];

  const scpResult = spawnSync('ssh', sshArgs, {
    stdio: [tarResult.stdout ? 'pipe' : 'inherit', 'inherit', 'inherit'],
    shell: false,
  });

  if (scpResult.error || scpResult.status !== 0) {
    console.error(`\nDeploy failed. Check your connection settings.`);
    console.error(`  Host: ${HOST}:${PORT}`);
    console.error(`  User: ${USER}`);
    console.error(`  Key:  ${KEY_PATH}`);
    process.exit(1);
  }

  console.log('\n✅ Deploy complete!');
  console.log(`   ${manifest.files.length} files deployed to ${TARGET_DIR}assets/react/`);
}

deploy().catch((err) => {
  console.error('Deploy failed:', err.message);
  process.exit(1);
});
