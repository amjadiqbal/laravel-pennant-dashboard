# Changelog

All notable changes to `laravel-pennant-dashboard` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.1] - 2026-10-21

### Security
- "Check value" and "What does one scope see?" no longer write. They used Pennant's `value()`, which
  stores the resolved value for a scope that was never checked, so a view-only user (or anyone in
  read-only mode) could create stored values through a GET request. They now preview without
  storing, caching or firing Pennant events.

### Fixed
- Rollout rules and per-scope overrides are now kept per Pennant store. A rollout set in one store
  no longer applies to a feature with the same name in another store. A new migration adds a `store`
  column and assigns existing rows to the default store, which is where 1.0.0 read them.
  `Rollout::decide()` and `HasRollout::rollout()` take an optional `store` argument.

### Changed
- README: what the audit log records for code and artisan changes, what lowering a rollout does,
  and what the scope search matches.

## [1.0.0] - 2026-10-21

### Added
- Gated Blade dashboard at `/pennant` listing every Pennant feature in a store: closures, class-based
  features (discovered from `app/Features`), lotteries and static values, plus stale stored features.
- Per-scope state: every stored value with a readable label, filters by scope type and on/off, and
  on/off counts per feature (database driver).
- Activate, deactivate or forget a feature for one scope: a user, a team, any Eloquent model,
  `Feature::globally()` or a raw stored scope string, with model search by name or email.
- Activate or deactivate for everyone through Pennant's `activateForEveryone()`.
- Sticky percentage rollouts: `Rollout::decide()` for closures and the `HasRollout` trait for classes;
  buckets are `crc32(feature|scope) % 100`, so raising the percentage only adds scopes. Stored values
  are re-bucketed when the percentage changes; per-scope overrides are kept.
- Purge one feature (optionally with its rollout) and purge every stale feature.
- Audit log of dashboard changes (actor, IP, old and new value) and of changes made by app code or
  artisan, prunable with `model:prune`.
- "What does one scope see?" view and a "Check value" lookup that explains overrides and buckets.
- Driver capability report (database, array, custom drivers), store picker for several stores.
- `viewPennantDashboard` and `managePennantDashboard` gates (local only by default), read-only mode,
  `php artisan about` entry.
