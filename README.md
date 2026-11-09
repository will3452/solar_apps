# Solar Project

A shared Laravel 13 application with accounts and a module dashboard. Solar Estimation Module is the first module.

## Open locally

```sh
php artisan serve --host=127.0.0.1 --port=8000
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000), create an account, then open Solar Estimation Module. Review the saved **Workbook example**, or choose **New estimate**. Enter sizing/site facts, review automatic materials and prices, then open **Customer proposal** to print or save as PDF.

PHP 8.3+, Composer 2, Node.js and npm are required. This Mac uses `/opt/homebrew/opt/php@8.3/bin/php` and `/opt/homebrew/bin/composer`. Run `composer run dev` for Vite hot reload and the queue worker. The local SQLite database and application key are already prepared.

## Authentication

Laravel Fortify handles login, logout, registration and password recovery, with Blade/Tailwind screens. Public registration is enabled by default. Set `AUTH_REGISTRATION_ENABLED=false` in `.env`, then clear configuration and route caches to disable both registration routes and the signup link. Authentication and user accounts are shared outside module-specific code; all signed-in users currently share the catalog and estimates.

Email uses `MAIL_MAILER=log`. Reset links appear in `storage/logs/laravel.log`; no external messages are sent. Real delivery requires a configured mail provider or SMTP host, port, credentials, scheme and sender address.

## Workbook data and calculations

```sh
php artisan migrate
php artisan db:seed
```

Seeding is idempotent, imports the 37 priced leaf BOM items from the bundled extracted source data, adds their dependencies and a workbook example, and preserves edited catalog records, saved estimates and accounts. It creates no user credentials. Source workbook names, rows, units and file hash are retained. The original Excel file is not edited or needed at runtime. Grouped subtotals and disconnected/error examples are not imported as chargeable materials.

The main flow automatically recalculates quantities and totals. Materials adjustments and additional works are editable. Pricing uses per-line buying cost divided by `(1 - gross margin)`, with a fixed discount before configurable tax. Calculations round monetary line totals to cents. The template's tax rate is an editable quote assumption, not a legal determination. Source prices are not current supplier prices. Saved estimates hold their own price/quantity/specification snapshot. Editing keeps saved prices unless **Refresh buying prices** is selected; saved pages and proposals do not silently recalculate.

Sizing uses monthly kWh, night share, sun hours, yield and selected equipment ratings. Physical inverter count is separate from kW rating. Battery capacity uses nominal V × Ah and the stated usable depth. Savings are capped at monthly consumption and the self-consumption assumption. Energy, savings and simple payback are estimates; monthly use does not establish peak electrical load.

Cable selection checks documented current/protection, corrected ampacity, resistive voltage drop, voltage rating, terminal fit and length per sale unit. It selects the smallest documented passing cross-section and records the reasons and route quantity. Circuit data may inherit inverter specifications; site values supply transaction-specific details. No ampacity table, manufacturer rating or wire-size-per-kW rule is invented. Missing circuit/specification data keeps a visible incomplete state and provisional source allowances. Roll/piece lengths remain unknown until supplied. AC reactance, surge/short-circuit duty, grounding design, string/MPPT design, battery BMS compatibility and installation approval require further project-specific validation. Source mounting/protection allowances need confirmation. Research links are in the catalog.

## Module boundaries

- `config/modules.php` supplies shared dashboard/navigation entries.
- `routes/modules/solar-estimation.php` owns module routes inside the shared authentication group.
- Module controllers, requests, models and services are under their respective `SolarEstimation` namespaces; views are in `resources/views/modules/solar-estimation`.
- The module's catalog and estimates use separate `solar_*` tables. Required-material links reject cycles and include shared materials once. New modules can add a route file, a registry entry and their own conventional Laravel code without a plugin framework.

## Checks

```sh
composer validate --strict
composer check-platform-reqs
composer test
npm run build
```

The frontend build downloads its configured Bunny Fonts and requires network access. Dependencies are locked in `composer.lock` and `package-lock.json`; use `composer install` and `npm ci` to reinstall them. Laravel Boost is installed for development, with project guidelines in `AGENTS.md`.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

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
