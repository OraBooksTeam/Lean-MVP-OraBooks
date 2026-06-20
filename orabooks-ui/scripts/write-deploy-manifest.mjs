import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const outDir = path.resolve(root, '../OraBooks Lean MVP/assets/react');

function walk(dir, base = '') {
  const entries = [];
  if (!fs.existsSync(dir)) {
    return entries;
  }

  for (const name of fs.readdirSync(dir)) {
    if (base === '' && name === 'deploy-manifest.json') {
      continue;
    }

    const full = path.join(dir, name);
    const rel = path.posix.join(base, name);
    if (fs.statSync(full).isDirectory()) {
      entries.push(...walk(full, rel));
    } else {
      entries.push(rel.replace(/\\/g, '/'));
    }
  }
  return entries;
}

const files = walk(outDir).sort();
const manifestPath = path.join(outDir, 'deploy-manifest.json');
let generatedAt = new Date().toISOString();

if (fs.existsSync(manifestPath)) {
  try {
    const existingManifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    if (JSON.stringify(existingManifest.files) === JSON.stringify(files)) {
      generatedAt = existingManifest.generated_at || generatedAt;
    }
  } catch {
    // Invalid or partial manifests are replaced below.
  }
}

const manifest = {
  generated_at: generatedAt,
  deploy_path: 'wp-content/plugins/OraBooks Lean MVP/assets/react/',
  files,
};

fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
console.log(`Deploy manifest written: ${files.length} files`);
