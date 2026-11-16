# Production

`docker/production/Dockerfile` builds one image from the root of the repository: FrankenPHP (`docker/production/app/Caddyfile`) with the application (no development dependencies, opcache with preloading, cache warmed), the web client (`front/dist`, built in the image, showing in its footer, after the name of the site, the release it was built for: the build argument `KYUDEN_VERSION`, the git tag in `release.yml`, `local` for `make prod-build`, `dev` otherwise) and, among its links, the official Discord server (the build argument `DISCORD_URL`, the variable `DISCORD_URL` of the repository in `release.yml`, `DISCORD_URL` of the shell for `make prod-build` and for the Vite server of development; no link when empty) and the Mercure hub. The same image runs the worker (`bin/console messenger:consume async`, the service `worker`, see `services.md`). It terminates TLS for the domain of `SERVER_NAME` (`kyuden.app`: a certificate from Let's Encrypt, renewed by Caddy and kept with the history of Mercure in the volume `caddy_data`; HTTP redirected to HTTPS; HSTS), or listens on HTTP only (`:80`). The site is behind Cloudflare (proxied, SSL in Full (strict): Caddy keeps its certificate): Symfony reads the address of the client from `X-Forwarded-For` when the request comes from one of the ranges of Cloudflare (`TRUSTED_PROXIES` of `symfony.env`, the trusted proxies of `config/packages/framework.yaml`; none by default), so that the limits of the sign-in by IP count each visitor apart. The files built by Vite are cached for a year (`/assets`, their names hashed); the rest of the web client (`index.html`, the service worker, the manifest) is revalidated on every load (`no-cache`), so that a new release reaches the players at once. The hub is a site of its own on port 8000 of the container (a configuration has a single hub, and each port is another server), which the host does not publish: Symfony publishes to it there (`MERCURE_URL`: `127.0.0.1:8000` for the application, `app:8000` for the worker), the site proxies the browsers to it on its origin (`/.well-known/mercure`, `MERCURE_PUBLIC_URL`). `compose.yaml` adds PostgreSQL and the worker. On every start of the application, the entrypoint of the image brings the database up to date before FrankenPHP serves anything: the Doctrine migrations (`migrations/`), the card database (`app:cards:import`) and the house bot (`app:house-bot:create`); a failed step stops the container. The worker runs none of these steps (the entrypoint runs them for FrankenPHP only) and starts once the application is healthy.

Continuous integration (GitHub Actions): every commit pushed, on any branch, runs `.github/workflows/test.yml`, the linters and the tests on the development stack (`CI` set: `make` runs `docker compose exec` without a terminal); a new commit on the branch cancels the run of the previous one. The tests of PHP run with the code coverage (`make coverage`): the HTML report is an artifact of the run (`coverage-report`), and the Clover report goes to Codecov (secret `CODECOV_TOKEN`, paths of the container mapped by `codecov.yml`). It caches the dependencies (PHP, npm, by their lock files) and the development image `symfony`. Releases: every git tag, on any branch, runs `.github/workflows/release.yml`: the same tests (`test.yml`), then the build of the image (`linux/amd64`), pushed to the GitHub Container Registry as `ghcr.io/alsciende/kyuden-app:<tag>` (public, no `latest`). The production host only needs `docker/production` (`compose.yaml`, `deploy.sh` and the two files below), not the code: it deploys the release named by `KYUDEN_VERSION` in `.env`. A tag `vX.Y.Z` of a commit of `main` is then deployed (job `deploy`, GitHub environment `production`, one deployment at a time): the workflow connects to the host over SSH as `deploy` with a key whose forced command is `deploy.sh`, sending the tag; the script checks it, writes it in `.env`, pulls the image of the application, stops the worker (so that its code does not run on the database the new release migrates), recreates the container of the application (not the database), waits for it to be healthy, then recreates the worker, restores `.env` if that fails, and prunes the unused images; the workflow then waits for `/api/actions` of the site (`DEPLOY_URL`) to answer, and purges from the cache of Cloudflare the public responses of the API (the prefixes `/api/cards` and `/api/actions`), when the zone is set.

```shell
cp docker/production/.env.dist docker/production/.env                # what the services share, and the release (KYUDEN_VERSION)
cp docker/production/symfony.env.dist docker/production/symfony.env  # the rest of the environment of the application
# replace every value of both files: those of the .dist files run the stack on a workstation, their secrets are public;
# SERVER_NAME=kyuden.app (its DNS pointing to the host, ports 80 and 443 open), HTTP_PORT=80, HTTPS_PORT=443;
# in symfony.env, SYMFONY_TRUSTED_HOSTS='^kyuden\.app$'
docker compose -f docker/production/compose.yaml --env-file docker/production/.env pull
docker compose -f docker/production/compose.yaml --env-file docker/production/.env up --detach
```

The continuous deployment needs, once, on the host (in `/opt/kyuden`, the files of `docker/production` and the two `.env`):

```shell
sudo useradd --create-home --shell /bin/sh --groups docker deploy  # no password: the key below only
sudo chown root:root /opt/kyuden /opt/kyuden/deploy.sh && sudo chmod 755 /opt/kyuden /opt/kyuden/deploy.sh
sudo chown root:deploy /opt/kyuden/.env && sudo chmod 660 /opt/kyuden/.env  # the only file deploy writes
sudo chown root:82 /opt/kyuden/symfony.env && sudo chmod 640 /opt/kyuden/symfony.env  # read by www-data (82) in the app container
ssh-keygen -t ed25519 -N '' -C github-actions-deploy -f deploy_key       # on a workstation, for this use only
# in ~deploy/.ssh/authorized_keys (700, 600, owned by deploy): the public key, restricted to the script
#   command="/opt/kyuden/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy
ssh-keyscan -t ed25519 <DEPLOY_HOST>   # compared with ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub on the host
```

In GitHub, the environment `production` (deployment tags `v*`) holds the secrets `DEPLOY_SSH_KEY` (the private key, then deleted from the workstation) and `DEPLOY_KNOWN_HOSTS` (the line of `ssh-keyscan`), and the variables `DEPLOY_HOST` (the name or IP the key is scanned for: `known_hosts` is indexed by it) and `DEPLOY_URL` (the URL of the site, without a trailing slash, e.g. `https://kyuden.app`); for the purge of the cache of Cloudflare, the variable `CLOUDFLARE_ZONE_ID` (no purge without it) and the secret `CLOUDFLARE_API_TOKEN` (a token of the zone allowed to purge its cache only). Being in the group `docker` amounts to root: the forced command is what keeps the key from doing anything but deploying a release.

`compose.yaml` and `deploy.sh` are copied again to the host when they change, with an account of the administrator (not `deploy`, whose key only runs `deploy.sh`): uploaded to their home, then installed by `install.sh` (`docker/production/install.sh`, copied once to the home of the administrator on the host, beside the directory `kyuden-upload`; `sudo install`: owned by `root`, `deploy.sh` in `755`, `compose.yaml` in `644`; it shows the differences, skips a file unchanged and lists `/opt/kyuden`):

```shell
scp docker/production/compose.yaml docker/production/deploy.sh <admin>@<DEPLOY_HOST>:kyuden-upload/
ssh -t <admin>@<DEPLOY_HOST> ./install.sh
```

Once, before: `ssh <admin>@<DEPLOY_HOST> mkdir -m 700 kyuden-upload`, then `scp docker/production/install.sh <admin>@<DEPLOY_HOST>:` (again when it changes).

`.env`, read by compose, holds what the services share (the database, the secret of Mercure): compose gives the application its `DATABASE_URL`, `MERCURE_URL`, `MERCURE_JWT_SECRET` (also the key of the tokens of the hub) and `SERVER_NAME`. The rest of the environment of the application is `symfony.env`, on the host, mounted read-only as its `.env` in the `app` container (it replaces the `.env` of the repository; the variables set by compose and the image, `APP_ENV` and `APP_DEBUG`, win over it), so that compose does not list every variable of Symfony. Both files stay out of git. The announcements of the games on Discord need `SITE_URL` and `DISCORD_GAMES_WEBHOOK_URL` in `symfony.env` (a webhook of the channel: Server Settings, Integrations, Webhooks).

To try the production stack on a workstation, stop the development stack first (`make down`: both listen on port 8080 if `HTTP_PORT` says so, and the targets below use a compose project of their own, `kyuden-prod`, so that `make down` does not remove its containers). The image is built from the working tree under the tag `local` (the image of a release is `linux/amd64` only), which the targets set as `KYUDEN_VERSION`:

```shell
make down
cp docker/production/.env.dist docker/production/.env                # as is: the values of the .dist files run locally
cp docker/production/symfony.env.dist docker/production/symfony.env
make prod-build   # the image, ghcr.io/alsciende/kyuden-app:local
make prod-up      # migrations, card database and house bot, then http://localhost:8080 (SERVER_NAME=localhost make prod-up: HTTPS on HTTPS_PORT, certificate of the local authority of Caddy)
make prod-logs
make prod-down    # with its volumes: the next start begins with an empty database
make up
```

The database of production holds no user of the fixtures: play as a guest or create an account.
