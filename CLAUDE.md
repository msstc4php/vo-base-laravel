# CLAUDE.md

Guidance for Claude Code in this repository. Deep references live under `.claude/docs/`.

## What this is

`msstc4php/vo-base-laravel`: an Eloquent cast, a validation rule, a request macro, a form request trait and route
parameter binding for `msstc4php/vo-base`. PHP >= 8.4, Laravel 13 components (`illuminate/*`); the HTTP parts need
`laravel/framework` (dev dependency, suggested) and come from the auto-discovered `ValueObjectServiceProvider`.
Layers in `deptrac.yaml`. Every conversion goes through the core's `ValueObjectMapper`.

## Common commands

Local install against the sibling `../vo-base`: `COMPOSER=composer-local.json composer update
--ignore-platform-req=php`. In CI the install is `composer-ci.json`.

- `COMPOSER=composer-local.json make check`, `make test`, `make infection`, `make fix`.

## Standard

Tool configuration is byte-identical to the `php84` templates of `msstc4symfony/bundle-standard`; CI's `templates` job
enforces it. Do not edit `.php-cs-fixer.dist.php`, `rector.php`, `phpstan*.neon`, `phpunit.xml.dist`,
`infection.json5`, `Makefile` or `.gitignore` here. The hex layout of that standard does not apply.
