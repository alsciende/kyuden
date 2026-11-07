#!/bin/sh
# Installs compose.yaml and deploy.sh, uploaded to ~/kyuden-upload, into /opt/kyuden (README, "Production").
# Runs on the host, as an administrator (sudo), not as deploy.
set -eu

src="$HOME/kyuden-upload"
dest=/opt/kyuden

step() { printf '\n==> %s\n' "$*"; }

# installs a file when it has changed: name, mode
put() {
  file=$1 mode=$2
  step "$file"
  if [ ! -f "$src/$file" ]; then
    echo "not in $src: skipped"
    return
  fi
  if sudo cmp -s "$src/$file" "$dest/$file"; then
    echo "unchanged"
  else
    sudo diff -u "$dest/$file" "$src/$file" || true
    sudo install -v -o root -g root -m "$mode" "$src/$file" "$dest/$file"
  fi
  rm "$src/$file"
}

step "installing from $src into $dest"
put deploy.sh    755
put compose.yaml 644

step "$dest"
ls -l "$dest"
