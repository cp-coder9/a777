# Shared-hosting cron

The dispatcher is bounded and overlap-safe at the database row level. Configure the hosting control-panel scheduler to run every minute (or the shortest supported interval):

```text
/usr/local/bin/php /absolute/private/path/to/current/cron/dispatch.php --once --limit=25
```

Use the PHP 8.3 binary shown by the control panel. The command emits one JSON line containing `claimed`, `succeeded`, `retried`, and `dead`; redirect it to a private rotating log if the scheduler does not retain output. Never place SMTP credentials in the command line.

The runtime configuration must provide `APP_URL`, stable `APP_KEY`, MariaDB settings, and SMTP settings. Without SMTP, the dispatcher can report zero work but verification/reset delivery cannot succeed and readiness remains unavailable. Failed delivery is retried with bounded exponential delay and controlled error codes; payloads remain AES-256-GCM encrypted in MariaDB.

After configuring cron:

1. Queue a staging verification message.
2. Confirm the next run claims and succeeds once.
3. Confirm the email arrives and its one-time link works.
4. Confirm a forced provider failure becomes `failed`, retries later, and eventually becomes `dead` at the configured attempt limit.
5. Record the scheduler timestamp and output as staged-release evidence.

If a run exits non-zero, check private structured logs and hosting mail/network policy. Do not print credentials, encrypted payloads, or raw provider responses into cron output.
