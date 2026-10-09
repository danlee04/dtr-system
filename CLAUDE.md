<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

# Security Guidelines

These rules apply to all code in this project. Follow them when writing or reviewing any Filament resource, Livewire component, controller, route, model, view, or config.

## CSRF

- Keep the `VerifyCsrfToken` middleware active on all stateful web routes. Avoid adding routes to `$except` unless truly necessary (e.g., verified external webhooks, which must use signature checks instead).
- For SPAs/APIs, configure Sanctum properly (stateful domains config) rather than disabling CSRF wholesale.

## XSS

- Default to `{{ }}` always. Only use `{!! !!}` for content that has been explicitly sanitized (e.g., with HTMLPurifier).
- Never render `request()->input()` raw in Blade or JS templates.

## SQL Injection

- Stick to Eloquent/Query Builder with parameter binding.
- If `whereRaw()`/`DB::raw()` is unavoidable, always use bindings: `whereRaw('name = ?', [$name])`. Never string-concatenate input.

## Mass Assignment

- Always define an explicit `$fillable` on models (avoid `$guarded = []`).
- Validate with Form Requests, then pass only validated fields to `create()`/`update()` (`$request->validated()`). Never pass `$request->all()` directly.

## Authentication & Authorization

- Use Policies/Gates consistently. Call `$this->authorize()` or `Gate::authorize()` in every controller action that touches user-owned data.
- Use Sanctum/Passport with correctly scoped tokens; set token abilities and expiration.
- Enable rate limiting on login and API routes (`throttle` middleware).

## Environment & Config

- `APP_DEBUG=false` in production, always.
- Keep `.env` out of version control. Rotate `APP_KEY` if it is ever leaked.
- Run `php artisan config:cache` in production so `.env` isn't read at runtime.

## File Uploads

- Validate with `mimes:`/`mimetypes:` rules, not just the file extension.
- Store uploads outside the public web root or on a disk that doesn't allow direct execution. Rename files on upload.

## General Hardening

- Run `composer audit` and keep dependencies updated.
- Force HTTPS (`URL::forceScheme('https')` or middleware) and set secure/HttpOnly cookies.
- Use security headers (CSP, X-Frame-Options) via middleware or a package like `spatie/laravel-csp`.

## Applying These Rules in Filament and Livewire

Most screens in this app are Filament resources and Livewire components, not controllers, so some of the rules above take a different form here:

- **Panel access:** `User` implements `FilamentUser`, and `canAccessPanel()` checks `is_active` and the role. Outside the `local` environment, Filament refuses every user when this is missing.
- **Authorization:** every resource has a Policy, which Filament checks for list, view, create, edit, and delete. Custom actions and pages must authorize on their own, with `->authorize()` or `Gate::authorize()` inside the action. Hiding a button is not authorization.
- **Livewire state:** the browser can change public properties and call public methods directly. Mark IDs and other server-owned values `#[Locked]`, and authorize inside every action method.
- **Validation:** Filament forms validate through field rules (`->required()`, `->rules()`, `->unique()`) and save only the fields in the form schema. That takes the place of Form Requests in resources; plain controller routes still use Form Requests. Models still define `$fillable`; never call `Model::unguard()`.
- **Raw HTML:** wrapping a value in `HtmlString`, or printing it with `{!! !!}` in a custom view, skips escaping. Use either only for markup the code builds itself, never for user-entered text such as names, remarks, or the user names read from the biometric device.
- **HTTPS:** force it only outside development. Laragon serves plain `http`, and a `Secure` session cookie is never sent back over `http`, which looks exactly like a broken login.
- **CSP:** Livewire and Alpine evaluate expressions at runtime, so a `script-src` policy needs `unsafe-eval` unless Livewire's `csp_safe` option is on. Filament has not been tested with `csp_safe` in this project. Until it has, send the other security headers without `script-src`, as hris does, rather than a policy that claims a protection it does not give.
