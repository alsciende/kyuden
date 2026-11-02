#!/usr/bin/env bash
# deploys a release on the production host: the forced command of the SSH key of the release workflow
# (.github/workflows/release.yml), in ~deploy/.ssh/authorized_keys:
#   command="/opt/kyuden/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy
# the command sent by the client is the git tag to deploy (SSH_ORIGINAL_COMMAND): read as data, never run
# installed in /opt/kyuden beside compose.yaml, .env and symfony.env (README, "Production"); owned by root, as its
# directory, so that the key can do nothing else: only .env is writable by deploy
set -euo pipefail

cd "$(dirname "$0")"

tag="${SSH_ORIGINAL_COMMAND:-}"
if [[ ! "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "invalid tag: expected vX.Y.Z" >&2
    exit 1
fi

previous="$(sed -n 's/^KYUDEN_VERSION=//p' .env)"
if [[ -z "$previous" ]]; then
    echo "KYUDEN_VERSION missing from .env" >&2
    exit 1
fi

# rewrites .env in place (the directory is not writable): a temporary file, then its content
set_version() {
    local tmp
    tmp="$(mktemp)"
    sed "s/^KYUDEN_VERSION=.*/KYUDEN_VERSION=$1/" .env > "$tmp"
    cat "$tmp" > .env
    rm -f "$tmp"
}

echo "deploying $tag (was $previous)"
set_version "$tag"
# the release that does not start leaves .env as it was
trap 'echo "deployment of $tag failed, KYUDEN_VERSION=$previous restored" >&2; set_version "$previous"' ERR

# the application only: the database keeps its image
docker compose pull app
docker compose up --detach --no-deps --force-recreate app
trap - ERR

docker image prune --force
