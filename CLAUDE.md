# CLAUDE.md

Guidance for Claude Code in this repository. Deep references live under `.claude/docs/`.

## What this is

`msstc4php/vo-base-laravel`: an Eloquent cast and a validation rule for `msstc4php/vo-base`. PHP >= 8.4,
Laravel 13 components (`illuminate/*`), no service provider. `Casts` and `Rules` never see each other
(`deptrac.yaml`). Every conversion goes through the core's `ValueObjectMapper`.

## Common commands

Local install against the sibling `../vo-base`: `COMPOSER=composer-local.json composer update
--ignore-platform-req=php`. In CI the install is `composer-ci.json`.

- `COMPOSER=composer-local.json make check`, `make test`, `make infection`, `make fix`.

## Standard

Tool configuration is byte-identical to the `php84` templates of `msstc4symfony/bundle-standard`; CI's `templates` job
enforces it. Do not edit `.php-cs-fixer.dist.php`, `rector.php`, `phpstan*.neon`, `phpunit.xml.dist`,
`infection.json5`, `Makefile` or `.gitignore` here. The hex layout of that standard does not apply.
