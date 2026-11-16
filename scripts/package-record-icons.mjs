import { mkdtemp, readFile, readdir, writeFile, stat } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';

// Presentation-only allowlist. No credentials, migration, data or fonts.
const files = [
    'app/Support/RecordActionIcons.php', 'app/Filament/Concerns/HasIconOnlyRecordActions.php',
    'app/Providers/AppServiceProvider.php', 'app/Filament/Pages/Households.php', 'app/Filament/Pages/HouseholdMembers.php',
    ...['Users/EditUser', 'Complaints/ViewComplaint', 'Complaints/EditComplaint',
        'ServiceRequests/EditServiceRequest', 'Certificates/EditCertificate', 'Payments/EditPayment',
        'AuditLogs/EditAuditLog', 'Announcements/EditAnnouncement', 'Homeowners/EditHomeowner',
        'DuesSettings/EditDuesSetting', 'ContactMessages/EditContactMessage'].map(value => {
        const [resource, page] = value.split('/');
        return `app/Filament/Resources/${resource}/Pages/${page}.php`;
    }),
    'app/Filament/Homeowner/Resources/ServiceRequests/Pages/ViewServiceRequest.php',
    'app/Filament/Homeowner/Resources/Complaints/Pages/ViewComplaint.php',
    'resources/views/components/record-icon.blade.php',
    'resources/views/filament/homeowner/pages/my-concerns.blade.php',
    'resources/views/portal/concerns.blade.php', 'resources/views/portal/requests/show.blade.php',
    'resources/views/portal/complaints/show.blade.php',
    'resources/views/portal/dashboard.blade.php',
    'resources/css/record-actions.css', 'resources/css/app.css', 'resources/css/filament.css', 'resources/js/locale.js',
    'lang/en.json', 'lang/fil.json',
];
async function walk(directory) {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
        const file = `${directory}/${entry.name}`;
        if (entry.isSymbolicLink()) throw new Error('Symlink in release');
        if (entry.isDirectory()) await walk(file); else files.push(file);
    }
}
await walk('public/build');
const checksums = {};
for (const file of files.sort()) {
    if (!(await stat(file)).isFile()) throw new Error(`Missing ${file}`);
    checksums[file] = createHash('sha256').update(await readFile(file)).digest('hex');
}
const directory = await mkdtemp(path.join(os.tmpdir(), 'hoa-record-icons-release-'));
await writeFile(path.join(directory, 'files.txt'), files.join('\n') + '\n', { mode: 0o600 });
const archive = path.join(directory, 'release.tar.gz');
const result = spawnSync('tar', ['-czf', archive, '-T', path.join(directory, 'files.txt')], { stdio: 'inherit', windowsHide: true });
if (result.error || result.status !== 0) throw new Error('Package failed');
const sha256 = createHash('sha256').update(await readFile(archive)).digest('hex');
await writeFile(path.join(directory, 'release-manifest.json'), JSON.stringify({ created: new Date().toISOString(), deployed: false, sha256, files: checksums }, null, 2) + '\n', { mode: 0o600 });
console.log(JSON.stringify({ directory, archive, sha256, files: files.length, deployed: false }));
