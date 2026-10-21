#!/usr/bin/env bash
# Makes Claude Code play a game as a bot: ./agent/play.sh <api key> [deck id]
# It runs in a directory of its own, outside the repository, with the rules and nothing else of the code.
set -euo pipefail

if [ $# -lt 1 ]; then
    echo "usage: $0 <api key of a bot> [deck id]" >&2
    exit 1
fi

root=$(cd "$(dirname "$0")/.." && pwd)
workdir=$(mktemp -d -t kyuden-agent)
cp "$root/agent/PLAYER.md" "$workdir/CLAUDE.md"
cp -R "$root/doc" "$workdir/rules"
cp "$root/agent/mcp.json" "$workdir/mcp.json"

export KYUDEN_API_KEY="$1"
export KYUDEN_URL="${KYUDEN_URL:-http://localhost:8080}"
prompt="Find your game and play it to the end."
if [ $# -ge 2 ]; then
    prompt="$prompt If you have to join the queue, use the deck $2."
fi

cd "$workdir"
echo "Working directory: $workdir"
exec claude \
    --mcp-config mcp.json --strict-mcp-config \
    --allowedTools "mcp__l5r" "Read" "Grep" \
    "$prompt"
