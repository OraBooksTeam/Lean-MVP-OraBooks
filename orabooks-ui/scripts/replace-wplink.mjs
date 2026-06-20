import fs from 'fs';
import path from 'path';

const root = path.resolve('src/pages/frontend');

function walk(dir, files = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) walk(p, files);
    else if (ent.name.endsWith('.tsx')) files.push(p);
  }
  return files;
}

for (const file of walk(root)) {
  let src = fs.readFileSync(file, 'utf8');
  if (!src.includes('react-router-dom') && !src.includes('<Link')) continue;

  const rel = path.relative(path.dirname(file), path.join(root, 'components/WpLink')).replace(/\\/g, '/');
  const importPath = rel.startsWith('.') ? rel : `./${rel}`;

  src = src.replace(/import \{ Link(?:, [^}]+)? \} from 'react-router-dom';\n?/g, '');
  src = src.replace(/import \{ [^}]*Link[^}]* \} from 'react-router-dom';\n?/g, '');
  src = src.replace(/<Link /g, '<WpLink ');

  if (src.includes('<WpLink') && !src.includes("from '../components/WpLink'") && !src.includes("from './WpLink'")) {
    const firstImportEnd = src.indexOf('\n') + 1;
    src = `${src.slice(0, firstImportEnd)}import WpLink from '${importPath}';\n${src.slice(firstImportEnd)}`;
  }

  fs.writeFileSync(file, src);
  if (src.includes('WpLink') || src.includes('react-router-dom')) {
    console.log('processed', path.basename(file));
  }
}
