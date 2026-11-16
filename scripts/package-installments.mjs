import { mkdtemp, readFile, readdir, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';

const files = [
    'app/Actions/Payments/RecordAdditionalPayment.php',
    'app/Actions/Payments/ConfirmReceiptDetails.php',
    'app/Actions/Payments/UpdatePaymentMetadata.php',
    'app/Models/Payment.php',
    'app/Models/DuesObligation.php',
    'app/Services/ButawReceiptEligibility.php',
    'app/Services/ButawReceipts.php',
    'app/Services/ButawReceiptSource.php',
    'app/Services/PaymentReceiptResolver.php',
    'app/Services/PaymentSettlementProtection.php',
    'app/Filament/Resources/Payments/Actions/InstallmentActions.php',
    'app/Filament/Resources/Payments/Actions/ReceiptActions.php',
    'app/Filament/Resources/Payments/Tables/PaymentsTable.php',
    'app/Filament/Homeowner/Resources/Payments/PaymentResource.php',
    'resources/views/filament/payments/installment-summary.blade.php',
    'resources/views/filament/payments/installment-history.blade.php',
    'resources/views/pdf/payment-receipt.blade.php',
    'lang/en.json', 'lang/fil.json',
    'database/migrations/2026_11_12_000001_add_monthly_settlement_receipt_identity.php',
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
for (const file of files.sort()) checksums[file] = createHash('sha256').update(await readFile(file)).digest('hex');
const directory = await mkdtemp(path.join(os.tmpdir(), 'hoa-installments-release-'));
await writeFile(path.join(directory, 'files.txt'), files.join('\n') + '\n');
const archive = path.join(directory, 'release.tar.gz');
const result = spawnSync('tar', ['-czf', archive, '-T', path.join(directory, 'files.txt')], { stdio: 'inherit', windowsHide: true });
if (result.error || result.status !== 0) throw new Error('Package failed');
const sha256 = createHash('sha256').update(await readFile(archive)).digest('hex');
await writeFile(path.join(directory, 'release-manifest.json'), JSON.stringify({ created: new Date().toISOString(), deployed: false, sha256, files: checksums }, null, 2) + '\n');
console.log(JSON.stringify({ directory, archive, sha256, files: files.length, deployed: false }));
