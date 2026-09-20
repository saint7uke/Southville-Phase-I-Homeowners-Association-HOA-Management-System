# Operations and Recovery

## Daily operation

- Keep Apache/PHP, MySQL, one queue worker, and the Laravel scheduler running.
- Review failed jobs, application errors, disk usage, and the age of the most recent backup.
- The scheduler generates monthly obligations on day 1, marks overdue obligations daily, evaluates delinquent accounts after the overdue sweep, and removes expired private report files while retaining their metadata.
- Admins can temporarily disable Staff or Homeowner access in System Settings. Admin access remains available for recovery.

## Monitoring thresholds

Alert the system owner when `/up` fails twice, any failed job appears, the oldest queued job exceeds five minutes, disk usage exceeds 80%, error rate rises above baseline, a TLS certificate has fewer than 14 days remaining, or the newest backup is older than 26 hours. `/up` is a readiness check: it returns success only when Laravel boots and the configured database accepts a read query. Never place passwords, uploaded documents, or raw personal data in monitoring messages.

## Backups

Back up both the MySQL database and the complete `storage/app` tree, including private resident documents and public announcement media. Encrypt the archive and copy it off-site. Retain seven daily, four weekly, and six monthly copies unless the HOA adopts a stricter retention policy.

Example MySQL database backup from an administrator terminal:

```powershell
& "F:\Xampp 8\mysql\bin\mysqldump.exe" --single-transaction --routines --triggers -u root -p hoa_system > hoa_system.sql
```

Store the resulting dump and a storage archive outside the web root. The redirection example is an operator command, not an application task.

## Restore drill

1. Create an isolated empty recovery database; never test a restore over production.
2. Import the selected database dump and restore uploaded files to an isolated application copy.
3. set a new application URL and keep outbound mail in `log` mode.
4. Run `php artisan migrate:status`, sign in with a controlled admin account, open representative homeowner/payment/case records, and verify one private file and certificate.
5. Record elapsed time, missing data, and the backup timestamp. Perform this drill at least quarterly.

Target recovery objectives for the capstone deployment are RPO 24 hours and RTO 4 hours. Revisit them if the HOA begins recording payments continuously throughout the day.

## Incident response

For suspected account compromise: disable the affected account, revoke sessions by changing its password/status, preserve audit logs, rotate exposed credentials, and document the time window. For lost private media: stop writes, preserve the current storage tree, restore to an isolated location, compare database paths, then copy only verified missing objects.
