# Contributing to Kinvo

Thanks for taking the time to contribute. This guide covers how to set up a local copy, the conventions the codebase follows, and what a change needs before it is ready for review.

Kinvo is proprietary commercial software. See [LICENSE.md](LICENSE.md) for the terms that apply to the code and to any changes you submit.

## Table of Contents

- [Getting Set Up](#getting-set-up)
- [Project Structure](#project-structure)
- [Branches and Commits](#branches-and-commits)
- [Coding Standards](#coding-standards)
- [Security Requirements](#security-requirements)
- [Accessibility](#accessibility)
- [Database Changes](#database-changes)
- [Testing Your Change](#testing-your-change)
- [Pull Requests](#pull-requests)
- [Reporting Bugs](#reporting-bugs)

## Getting Set Up

There is no build step, no package manager, and no dependency install. Kinvo is vanilla PHP served directly.

**Requirements**

- PHP 7.4 or newer (8.0+ recommended) with `pdo`, `pdo_mysql`, `session`, `json`, and `mbstring`
- MySQL 5.7+ or MariaDB 10.2+

**Steps**

1. Clone the repository and create an empty MySQL database.
2. Import the schema: `mysql -u USER -p DATABASE < database_schema.sql`
3. Copy `includes/example_config.php` to `includes/config.php` and fill in your database credentials. This file is gitignored and must never be committed.
4. Start a local server from the repository root: `php -S localhost:8000`
5. Visit `http://localhost:8000/install.php` and complete the wizard, or create `includes/.installed` by hand if you imported the schema yourself. `index.php` redirects to the installer until both `includes/config.php` and `includes/.installed` exist.
6. Sign in to the admin portal at `/admin/login.php`. Change the default password immediately.

The client portal lives at `/client/login.php` and uses PIN-based authentication. Public invoice and estimate links live under `/public/` and are reached by `unique_id`, with no login.

Full installation notes are in [INSTALL.md](INSTALL.md), and feature documentation is in [USER_GUIDE.md](USER_GUIDE.md).

## Project Structure

| Path | Contents |
| --- | --- |
| `admin/` | Admin portal pages: invoices, estimates, customers, payments, settings |
| `client/` | Customer-facing portal |
| `public/` | Unauthenticated views reached by `unique_id` |
| `ajax/` and `admin/ajax/` | Endpoints that return JSON to the portal pages |
| `includes/` | Shared code: database connection, helper functions, header, footer, config |
| `assets/` | Static images and icons |
| `website/` | Marketing site, separate from the application |
| `database_schema.sql` | Full schema, the source of truth for table structure |

Shared helpers are split by domain: `functions.php` (auth, validation, security, formatting), `estimate-functions.php`, `payment-functions.php`, `photo-functions.php`, and `service-functions.php`. Put new helpers in the file that matches the domain rather than growing `functions.php`.

## Branches and Commits

- Branch off `main`. Use a short descriptive name such as `fix-zero-dollar-line-items` or `feature/recurring-invoices`.
- Keep each commit to one logical change.
- Write commit subjects in the imperative mood, prefixed with the type of change:

  ```
  feat: paginate invoices and estimates, remove per-row queries
  fix: show the actual line items before copying a past invoice
  fix(a11y): unnest edit link from label on record-payments rows
  refactor(invoices): rebuild list as four columns instead of nine
  chore: untrack includes/config.php
  ```

  Common types are `feat`, `fix`, `refactor`, `chore`, `docs`, and `perf`. An optional scope in parentheses narrows it to an area.
- Delete your branch once it is merged.

## Coding Standards

**PHP**

- Four spaces for indentation, never tabs.
- Functions are `camelCase`. Database columns and array keys taken from query results are `snake_case`, matching the schema.
- Every page starts by requiring what it needs, setting security headers, then enforcing access:

  ```php
  <?php
  require_once '../includes/db.php';
  require_once '../includes/functions.php';

  setSecurityHeaders(true, true);
  requireAdmin();
  ```

  Client portal pages call `requireClientLogin()` instead.
- All database access goes through the shared `$pdo` connection from `includes/db.php` using prepared statements. Never interpolate a variable into SQL.
- Validate input with the existing helpers rather than writing new checks: `validateAndSanitizeString()`, `validateEmail()`, `validatePhone()`, `validateCurrency()`, `validateInteger()`, `validateDate()`, `validateSelect()`. Composite validators such as `validateCustomerData()`, `validateInvoiceData()`, and `validatePaymentData()` cover whole forms.
- Escape on output with `htmlspecialchars()`. Assume every value that reaches a template is untrusted, including values you just wrote to the database.
- Format money with `formatCurrency()` so totals stay consistent across the portals and printed invoices.
- Report failures through `handleDatabaseError()`, `handleSecureError()`, and `setFlashMessage()`. Do not echo exception messages or stack traces to the browser.
- Avoid per-row queries inside loops. Fetch what a list needs in one query with joins or aggregates.

**Front end**

- Styling is Tailwind utility classes loaded from a CDN, with Font Awesome for icons. There is no compile step, so write utilities directly in the markup.
- Reach for the lowest level of responsive technique that works: intrinsic sizing such as `clamp()`, `flex-wrap`, and `minmax()` grids first, and media queries only for viewport-level concerns.
- JavaScript is plain and inline to the page it serves. Keep it small and progressive: a page should still render and submit without it wherever that is reasonable.

**Everywhere**

- No comments. If a block needs a comment to be understood, name the variables and functions so it does not.
- No debug artifacts. Remove every `console.log`, `var_dump`, `print_r`, and stray `error_log` before you commit, along with any scratch or test files you created.
- No commented-out code. Delete it; the history keeps it.
- Fix a repeated defect in the shared layer rather than patching each page that shows it.

## Security Requirements

Security is not optional review polish. A change that touches any of the following must satisfy it before it is submitted:

- **CSRF.** Every state-changing form includes `getCSRFTokenField()`, and the handler calls `requireCSRFToken()` before it acts.
- **SQL injection.** Prepared statements with bound parameters, always.
- **XSS.** Escape output with `htmlspecialchars()`. Use `sanitizeHtml()` when limited markup genuinely has to survive.
- **Access control.** Admin pages call `requireAdmin()`. Client pages call `requireClientLogin()` and then confirm the record belongs to that client through `requireResourceOwnership()`, `requireInvoiceOwnership()`, `requirePaymentOwnership()`, or `requireClientAccess()`. Never trust an ID from the request to imply ownership.
- **Security headers.** Call `setSecurityHeaders()` at the top of every page that renders HTML.
- **Rate limiting.** New authentication or high-cost endpoints go through `checkRateLimit()`, `recordFailedAttempt()`, and `recordSuccessfulAttempt()`. Current limits are documented in [RATE_LIMITING.md](RATE_LIMITING.md).
- **Secrets.** Credentials belong in `includes/config.php` or a config file outside the web root loaded by `includes/config_loader.php`. Nothing secret goes into the repository, and nothing secret goes into a log line.

If you find a security vulnerability, do not open a public issue. Email support@kinvo.app with the details and give us a chance to ship a fix before disclosing it.

## Accessibility

The target is WCAG 2.2 AA, and regressions are treated as bugs.

- Contrast of at least 4.5:1 for body text, 3:1 for large text and for meaningful non-text elements such as borders and focus rings.
- Semantic HTML before ARIA. Landmarks, one `h1` per page, no skipped heading levels, links that navigate, buttons that act.
- Everything reachable and operable by keyboard, with a visible `:focus-visible` ring. Never remove an outline without replacing it.
- Interactive targets at least 24x24 pixels, larger for primary mobile actions.
- Every input has a label, and error messages say both what is wrong and what format is expected.
- Do not nest an interactive element inside a label or another interactive element.

## Database Changes

- Add new tables and columns to `database_schema.sql` using `CREATE TABLE IF NOT EXISTS`, matching the existing naming and foreign key style.
- Keep the installer working. `install.php` builds a fresh database from the schema, so a change that only exists in your local database will break new installs.
- Include the SQL an existing installation needs to catch up in your pull request description.
- Never commit data from a real installation.

## Testing Your Change

There is no automated test suite. Verification is manual, and it is the contributor's job.

1. Check syntax on every file you touched: `php -l path/to/file.php`
2. Exercise the exact path you changed through the browser, not a nearby page. If you changed invoice creation, create an invoice and confirm the row, the totals, and the rendered invoice.
3. Test both portals when a change spans them, plus the public link view where relevant.
4. Test at a narrow viewport and with the keyboard alone.
5. Confirm the failure cases: invalid input, a missing record, a request without a valid CSRF token, and a client trying to reach another client's record.
6. Remove any test customers, invoices, estimates, and payments you created.

Describe in your pull request what you actually exercised, and say plainly what you did not cover.

## Pull Requests

Before opening one:

- [ ] `php -l` passes on every changed file
- [ ] The changed path was exercised end to end in a browser
- [ ] No comments, debug output, or commented-out code
- [ ] No credentials, config files, logs, or test data committed
- [ ] CSRF, escaping, prepared statements, and ownership checks in place
- [ ] Keyboard and contrast checked on any UI change
- [ ] Schema changes reflected in `database_schema.sql`

In the description, cover what changed, why, how you tested it, and any migration step an existing installation needs. Screenshots help for UI work. Keep the pull request focused on one thing so it can be reviewed quickly.

## Reporting Bugs

Open an issue with:

- What you expected and what happened instead
- Steps to reproduce, starting from a known state
- Which portal and which page
- PHP and MySQL versions, and browser if the problem is visual
- Any error from `logs/` with credentials and customer data removed

For styling problems that only appear on a live server, check [PRODUCTION_CSS_TROUBLESHOOTING.md](PRODUCTION_CSS_TROUBLESHOOTING.md) first.
