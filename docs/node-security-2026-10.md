# Node.js security baseline (2026-10-31)

Docker previously used Node.js 20 in all three stages and CI requested 20.
Node.js 20 is end-of-life and should be considered affected by new security
releases even when it is not listed among supported versions receiving fixes.

Use Node.js 24.22.1 LTS, including the October 21 security fixes for Brotli
(CVE-2026-111497), DNS/TLS validation and other runtime vulnerabilities.
Docker pins this release in dependency, build and production stages.
`frontend/.nvmrc` selects the same version locally and in both frontend CI jobs;
`engines.node` declares the supported patched 24.x runtime range. npm normally
warns about incompatible engines; Docker and CI explicitly select the runtime.
No npm dependency versions change in this runtime update.

Official sources:

- [October 2026 security releases](https://nodejs.org/en/blog/vulnerability/october-2026-security-releases)
- [Node.js 24.22.1 LTS release](https://nodejs.org/en/blog/release/v24.22.1)
- [Node.js release support status](https://nodejs.org/en/about/previous-releases)

The frontend is a Next.js Node server. Exact exploitability depends on how
malicious data reaches runtime APIs or dependencies; absence of an explicit
Brotli import in application code does not establish that the runtime is safe.
Live production versions were not inspected. This PR changes source and CI,
not existing containers or the host's Node.js installation.

## Apply and verify

```sh
cd frontend
nvm install
nvm use
npm ci
npm run lint
npm run build
npm run e2e
```

From the repository root, rebuild and recreate the frontend container:

```sh
docker compose build --pull web
docker compose up -d --force-recreate web
docker compose exec web node --version
```

Rebuild and redeploy the production image as well; verify `node --version`
inside every running instance. Reusing an old image does not apply the fix.
Update Docker tags and `.nvmrc` together for subsequent security releases.
