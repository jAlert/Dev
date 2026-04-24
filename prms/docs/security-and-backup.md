# Security and Backup

## Security

### Two-Factor Authentication (2FA)
- TOTP-based (Time-based One-Time Password) via authenticator app
- Users can enable/disable 2FA from their profile page (`/profile`)
- On login, users with 2FA enabled are redirected to a verification screen before accessing the app
- 2FA secret stored encrypted in `users.two_factor_secret`; confirmed timestamp in `users.two_factor_confirmed_at`

### Account Activation
- Admins can enable or disable user accounts from `/admin/users`
- Disabled accounts (`is_active = false`) cannot log in
- Super admin accounts cannot be disabled via the UI
- Disabled users appear greyed out with a "Disabled" badge in the user list

### Role-Based Access Control
- Powered by Spatie Laravel Permission 7.2
- Permissions are module-scoped: `view-{slug}`, `create-{slug}`, `edit-{slug}`, `delete-{slug}`, `review-{slug}`, `approve-{slug}`, `change-status-{slug}`
- Super admin role bypasses all gates via `Gate::before` in `AppServiceProvider`
- Stage-specific role enforcement: if a workflow stage has `approver_role_id`, only that role can act on it — no fallback to general permissions

### API Authentication
- Laravel Sanctum token-based auth for all `/api/dynamic/*` endpoints
- Tokens scoped per user, managed via the API Manager UI

### CSRF Protection
- All Livewire and standard form submissions include CSRF tokens
- API routes use Sanctum token auth (no CSRF required)

### Edit Locking per Stage
- Each workflow stage has an `allow_edit` flag
- When disabled, the Edit button is hidden regardless of user permissions — prevents tampering with records under active review

---

## Backup and Recovery

### Snapshots for Recovery
- Take VM/container snapshots before major migrations
- Database: `mysqldump prms > prms_backup_$(date +%F).sql`

### Docker Container Backup
- Named volumes (`prms_build`, etc.) should be backed up with `docker run --rm -v prms_build:/data -v $(pwd):/backup alpine tar czf /backup/prms_build.tar.gz -C /data .`
- `.env` file is not tracked in git — keep a secure copy outside the repo
