# PHP security baseline (2026-10-31)

## Required runtime versions

PHP fixed CVE-2026-109618 and CVE-2026-109619 in 8.2.35, 8.3.36,
8.4.27 and 8.5.12. The application retains PHP 8.3 for Docker and pins
`php:8.3.36-fpm`. Composer rejects older patches in every supported series;
CI tests all four fixed versions on Ubuntu and macOS. No application package
upgrade is required for these PHP runtime vulnerabilities.

The October 23 advisory additionally recommends disabling session upload
progress until the next regular bugfix release. `backend/php-security.ini`
applies `session.upload_progress.enabled=0` before request parsing. Keep this
setting on every web runtime, including custom CLI-server deployments.

Official sources:

- [October 21 critical security advisory](https://www.php.net/archive/2026.php#2026-10-21-5)
- [October 23 session upload progress advisory](https://www.php.net/archive/2026.php#2026-10-23-1)
- [PHP 8 changelog](https://www.php.net/ChangeLog-8.php)

## Rebuild and deployment verification

Pulling or merging source does not update existing PHP workers. Rebuild and
recreate the development service:

```sh
docker compose build --pull api
docker compose up -d --force-recreate api
docker compose exec api php -v
docker compose exec api php -r 'var_dump(ini_get("session.upload_progress.enabled"));'
```

For production, rebuild with the fixed PHP release, load the security INI
before request parsing, restart every worker and verify the actual runtime
version. Composer requirements alone do not patch PHP. Vendor-backported
distribution packages need separate vendor verification.

The README references Render configuration, but `render.yaml` and
`backend/Dockerfile.render` are not tracked on the reviewed main branch.
An existing local checkout contains an untracked Render Dockerfile based on
`php:8.3-cli`; it must use `php:8.3.36-cli` and copy `php-security.ini` into
`/usr/local/etc/php/conf.d/zz-security.ini` if used for deployment. Those local
deployment drafts are not included in this change. Live production/container
versions were not accessible, so deployment remediation is not confirmed.

The local Homebrew CLI reports PHP 8.5.6 and also needs updating to at least
8.5.12 separately. This repository change does not alter the host installation.
Windows PHP packages additionally need the October 26 `-1` rebuild for the
Brotli fix; the repository Docker image uses Linux.
