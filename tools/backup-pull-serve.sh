#!/bin/sh
# Forced command for the Mac's backup-pull SSH key: a READ-ONLY rsync of ~/dot-backups, and nothing else.
set -f
cd "$HOME" || exit 1
deny() { echo "denied" >&2; echo "$(date +%FT%T) $SSH_ORIGINAL_COMMAND" >> "$HOME/backup-pull-denied.log"; exit 1; }
# shellcheck disable=SC2086
set -- $SSH_ORIGINAL_COMMAND
[ "$#" -ge 6 ] || deny
[ "$1" = rsync ] && [ "$2" = --server ] && [ "$3" = --sender ] || deny
shift 3
last=""; prev=""
for a in "$@"; do prev="$last"; last="$a"; done
[ "$prev" = . ] || deny
case "$last" in dot-backups|dot-backups/) : ;; *) deny ;; esac
n=$#; i=0
for a in "$@"; do
  i=$((i + 1))
  if [ "$i" -gt $((n - 2)) ]; then break; fi
  case "$a" in
    --dirs) ;;
    -[A-Za-z0-9.=]*) case "${a#-}" in *[!A-Za-z0-9.=]*) deny ;; esac ;;
    *) deny ;;
  esac
done
exec rsync --server --sender "$@"
