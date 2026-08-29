# Community Messaging shared-hosting operations

This module is deployed by FTP with the rest of `app/public` and uses the same MariaDB database, session authentication and organisation model. It does not create a second database.

## Platform moderator provisioning

Organisation roles do not grant platform-wide community moderation. Provision the first reviewed moderator through the hosting control panel's phpMyAdmin SQL console after that user has registered and verified their email:

```sql
INSERT INTO community_platform_moderators(user_id,status,grant_reason)
SELECT id,'active','Approved platform community moderator.'
FROM users
WHERE email='REPLACE-WITH-REVIEWED-EMAIL'
  AND status='active'
  AND email_verified_at IS NOT NULL;
```

The statement inserts nothing if the identity is not active and email-verified. Confirm the exact result:

```sql
SELECT u.email,m.status,m.grant_reason,m.granted_at
FROM community_platform_moderators m
JOIN users u ON u.id=m.user_id
ORDER BY m.granted_at;
```

Revoke without deleting the audit-relevant grant record:

```sql
UPDATE community_platform_moderators m
JOIN users u ON u.id=m.user_id
SET m.status='revoked',m.revoked_at=UTC_TIMESTAMP(6)
WHERE u.email='REPLACE-WITH-REVIEWED-EMAIL';
```

## Digest delivery cron

In the hosting control panel, schedule the PHP 8.3 CLI command every five minutes. Replace the absolute path with the account's real deployment path:

```text
/usr/local/bin/php /ABSOLUTE-PATH/app/bin/deliver-community-notifications.php
```

The command claims at most 500 due outbox rows per run, uses row locks to avoid duplicate claims, and prints delivered/remaining counts. Daily and weekly items remain scheduled until their `available_at` time; watched-channel and direct-mention items are immediately due.

Before staged promotion, run the command once from the control-panel cron runner and confirm a successful log line such as:

```text
community_notifications_delivered=0 remaining=0
```

SMTP/push delivery is a separate release gate. This worker marks the in-platform outbox delivery state used by the Community Digest surface; it does not claim external email, SMS, WhatsApp or mobile push delivery.
