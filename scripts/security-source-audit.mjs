import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { extname, normalize } from 'node:path';

const root = process.cwd();
const json = process.argv.includes('--json');
const tracked = execFileSync('git', ['ls-files', '-co', '--exclude-standard'], {
    cwd: root,
    encoding: 'utf8',
}).split(/\r?\n/).filter(Boolean).map((path) => normalize(path).replaceAll('\\', '/'));

const secretNames = [
    /(^|\/)\.env($|\.)/i,
    /(^|\/)(auth\.json|\.npmrc|\.pypirc|id_rsa|id_ed25519)$/i,
    /\.(pem|p12|pfx|key)$/i,
];
const sourceRoots = /^(app|bootstrap|config|database|resources|routes|scripts)\//;
const sourceFiles = /^(artisan|composer\.json|package\.json|vite\.config\.[cm]?js)$/;
const textExtensions = new Set(['.php', '.js', '.mjs', '.cjs', '.jsx', '.ts', '.tsx', '.ps1', '.bat', '.cmd', '.json', '.yml', '.yaml']);
const rules = [
    ['remote_dynamic_import', /import\s*\(\s*['"]https?:\/\//i],
    ['remote_eval_fetch', /eval\s*\([^)]*(fetch|axios|https?\.)/is],
    ['download_and_execute', /(Invoke-Expression|\biex\b|DownloadString|curl[^\r\n|]*\|[^\r\n]*(sh|bash|powershell))/i],
    ['decoded_code_execution', /(eval|assert|Function)\s*\(\s*(base64_decode|atob)\s*\(/i],
    ['node_remote_execution', /(child_process|execSync|spawnSync)[\s\S]{0,240}https?:\/\//i],
];

const findings = [];
for (const path of tracked) {
    if (secretNames.some((pattern) => pattern.test(path)) && path !== '.env.example') {
        findings.push({ rule: 'tracked_secret_filename', path });
    }
    if (path === 'scripts/security-source-audit.mjs'
        || !(sourceRoots.test(path) || sourceFiles.test(path))
        || (!textExtensions.has(extname(path)) && !sourceFiles.test(path))) {
        continue;
    }
    let contents;
    try {
        contents = readFileSync(path, 'utf8');
    } catch {
        findings.push({ rule: 'unreadable_source_file', path });
        continue;
    }
    for (const [rule, pattern] of rules) {
        if (pattern.test(contents)) {
            findings.push({ rule, path });
        }
    }
}

const result = {
    passed: findings.length === 0,
    scanned_files: tracked.length,
    findings,
    note: 'Findings contain rule names and paths only; file contents and secret values are never emitted.',
};

if (json) {
    process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
} else if (result.passed) {
    process.stdout.write(`Security source audit passed (${result.scanned_files} repository files inventoried).\n`);
} else {
    process.stderr.write('Security source audit failed:\n');
    for (const finding of findings) process.stderr.write(`- ${finding.rule}: ${finding.path}\n`);
}

process.exitCode = result.passed ? 0 : 1;
