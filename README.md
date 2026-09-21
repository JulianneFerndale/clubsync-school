<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## ClubSync — Data Retention & Storage

ClubSync runs on a Supabase Postgres database (free tier ≈ 500 MB). A daily
`retention:check` command keeps the database from filling up:

- **Auto-prunes only transient rows** — expired sessions and *read* notifications
  older than `RETENTION_READ_NOTIF_DAYS`. Student, financial, activity, and audit
  data are never deleted automatically.
- **Alerts admins** when usage crosses `RETENTION_WARN_PERCENT` / `RETENTION_CRITICAL_PERCENT`,
  linking them to **Admin → Storage & Retention**.
- On that page an admin can **archive an old semester**: its attendance and fee
  payments are exported to a downloadable Excel file, then removed from the database.
  The current and previous semester are always kept; audit logs are retained for at
  least one academic year (per `POLICY.md`).

### Settings (`.env`)

| Variable | Default | Meaning |
| --- | --- | --- |
| `DB_CAP_MB` | `500` | Database size cap to measure against (raise if you upgrade the plan) |
| `RETENTION_WARN_PERCENT` | `75` | Usage % that triggers an admin alert |
| `RETENTION_CRITICAL_PERCENT` | `90` | Usage % for a critical alert |
| `RETENTION_SESSION_DAYS` | `7` | Age (days) after which expired sessions are pruned |
| `RETENTION_READ_NOTIF_DAYS` | `90` | Age (days) after which read notifications are pruned |
| `RETENTION_AUDIT_DAYS` | `365` | Audit-log retention (floored at 365 per policy) |

### Required cron (production)

The daily prune + alert relies on Laravel's scheduler, so add **one** cron entry on the server:

```cron
* * * * * cd /path/to/clubsyncing && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, create a Task Scheduler task that runs `php artisan schedule:run` every minute.
You can also run the check on demand at any time:

```bash
php artisan retention:check
```

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
