import { mkdtemp, readFile, readdir, writeFile, stat } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import os from 'node:os';

// Explicit runtime allowlist: never package environment files, customer data,
// proprietary certificate fonts, source documents, tests, or the root .htaccess.
const files = [
    'app/Actions/Certificates/ManageCertificateCollection.php',
    'app/Actions/ContactMessages/CreateResidentInquiry.php',
    'app/Actions/Dues/PrepareMonthlyAssessment.php',
    'app/Actions/Homeowners/RegisterHomeowner.php',
    'app/Actions/Payments/RecordPayment.php',
    'app/Actions/Projects/ManageProject.php',
    'app/Actions/Users/ChangeAccountStatus.php',
    'app/Filament/Homeowner/Pages/AnnualDues.php',
    'app/Filament/Homeowner/Pages/MyConcerns.php',
    'app/Filament/Homeowner/Pages/MyProfile.php',
    'app/Filament/Homeowner/Resources/Certificates/CertificateResource.php',
    'app/Filament/Homeowner/Resources/Certificates/Pages/ListCertificates.php',
    'app/Filament/Homeowner/Resources/Complaints/ComplaintResource.php',
    'app/Filament/Homeowner/Resources/Complaints/Pages/CreateComplaint.php',
    'app/Filament/Homeowner/Resources/ServiceRequests/ServiceRequestResource.php',
    'app/Filament/Homeowner/Resources/ServiceRequests/Pages/CreateServiceRequest.php',
    'app/Filament/Homeowner/Resources/ServiceRequests/Pages/ViewServiceRequest.php',
    'app/Filament/Pages/AnnualDues.php', 'app/Filament/Pages/HouseholdMembers.php',
    'app/Filament/Pages/Households.php', 'app/Filament/Pages/Projects.php',
    'app/Filament/Pages/Reports.php', 'app/Filament/Pages/SmsNotifications.php',
    'app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php',
    'app/Filament/Resources/Certificates/Tables/CertificatesTable.php',
    'app/Filament/Resources/Homeowners/Tables/HomeownersTable.php',
    'app/Filament/Resources/Payments/Schemas/PaymentForm.php',
    'app/Filament/Resources/Roles/RoleResource.php',
    'app/Filament/Resources/ServiceRequests/Tables/ServiceRequestsTable.php',
    'app/Filament/Resources/Users/Schemas/UserForm.php',
    'app/Filament/Resources/Users/Tables/UsersTable.php',
    'app/Http/Controllers/CertificateDownloadController.php',
    'app/Http/Controllers/CertificateDraftController.php',
    'app/Http/Controllers/ContactMessageController.php',
    'app/Http/Controllers/Portal/ConcernController.php',
    'app/Http/Controllers/Portal/ServiceRequestController.php',
    'app/Http/Controllers/SmsVerificationController.php',
    'app/Http/Requests/Portal/RegisterHomeownerRequest.php',
    'app/Http/Requests/Portal/StoreComplaintRequest.php',
    'app/Http/Requests/StoreContactMessageRequest.php',
    'app/Models/Certificate.php', 'app/Models/ContactMessage.php',
    'app/Notifications/CertificateIssued.php',
    'app/Observers/WorkflowNotificationObserver.php',
    'app/Providers/Filament/HomeownerPanelProvider.php',
    'app/Providers/Filament/StaffPanelProvider.php',
    'app/Services/AnnualDuesSummary.php', 'app/Services/CertificateDuesEligibility.php',
    'app/Services/ResidentConcerns.php', 'app/Services/ResidencyCertificatePreviews.php',
    'app/Support/CurrentPropertyAddress.php',
    'app/Validation/ComplaintSubmissionValidator.php', 'app/Validation/PaymentValidator.php',
    'config/livewire.php',
    'database/migrations/2026_11_12_000001_add_resident_concerns_and_certificate_collection.php',
    'lang/en.json', 'lang/fil.json', 'routes/web.php',
    'resources/css/app.css', 'resources/css/landing.css', 'resources/css/filament.css',
    'resources/css/workflow-revisions.css', 'resources/js/app.js', 'resources/js/resident-concerns.js', 'resources/js/locale.js',
    'resources/js/react/LandingPage.jsx', 'resources/js/react/landing/ContactSection.jsx',
    'resources/js/react/landing/content.js', 'resources/js/react/landing/HowItWorksSection.jsx',
    'resources/views/filament/audit-details.blade.php',
    'resources/views/filament/pages/annual-dues.blade.php',
    'resources/views/filament/pages/reports.blade.php',
    'resources/views/filament/homeowner/certificate-pickup-notice.blade.php',
    'resources/views/filament/homeowner/pages/my-concerns.blade.php',
    'resources/views/filament/homeowner/pages/my-profile.blade.php',
    'resources/views/filament/homeowner/pages/my-household.blade.php',
    'resources/views/filament/homeowner/widgets/welcome.blade.php',
    'resources/views/layouts/portal.blade.php', 'resources/views/portal/concerns.blade.php',
    'resources/views/portal/auth/register.blade.php', 'resources/views/portal/profile/edit.blade.php',
    'resources/views/portal/requests/index.blade.php', 'resources/views/portal/requests/show.blade.php', 'resources/views/portal/requests/create.blade.php',
];
async function walk(directory) {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
        const name = `${directory}/${entry.name}`;
        if (entry.isSymbolicLink()) throw new Error('Release assets cannot contain symbolic links.');
        if (entry.isDirectory()) await walk(name); else files.push(name);
    }
}
await walk('public/build');
const checksums = {};
for (const file of files.sort()) {
    if (!(await stat(file)).isFile()) throw new Error(`Missing runtime file: ${file}`);
    checksums[file] = createHash('sha256').update(await readFile(file)).digest('hex');
}
const directory = await mkdtemp(path.join(os.tmpdir(), 'hoa-interface-release-'));
const list = path.join(directory, 'files.txt');
await writeFile(list, files.join('\n') + '\n', { mode: 0o600 });
const archive = path.join(directory, 'release.tar.gz');
const result = spawnSync('tar', ['-czf', archive, '-T', list], { stdio: 'inherit', windowsHide: true });
if (result.error || result.status !== 0) throw new Error('Release archive creation failed.');
const sha256 = createHash('sha256').update(await readFile(archive)).digest('hex');
await writeFile(path.join(directory, 'release-manifest.json'), JSON.stringify({ created: new Date().toISOString(), deployed: false, sha256, files: checksums }, null, 2) + '\n', { mode: 0o600 });
console.log(JSON.stringify({ directory, archive, sha256, files: files.length, deployed: false }));
