#!/usr/bin/env bash
# git-easy-push.sh
# A small helper script to initialize a repo (optional), add files, commit, set remote and push.
# Usage examples:
#  ./scripts/git-easy-push.sh -m "first commit" -r https://github.com/Abhishek-kmr525/POST7493u93.git -b main README.md
#  ./scripts/git-easy-push.sh --init --remote origin --url <url> -m "msg" --push

set -euo pipefail

prog_name="git-easy-push.sh"

usage() {
  cat <<EOF
Usage: $prog_name [options] [--] [paths...]

Options:
  -m, --message MSG       Commit message (default: "auto commit")
  -b, --branch BRANCH     Branch name to push (default: main)
  -r, --remote NAME       Remote name (default: origin)
  -u, --url URL           Remote URL to set for the remote name
  --init                  Run 'git init' if repository is not initialized
  --push                  Push after commit (default: yes)
  --force                 Force push (use with caution)
  -n, --dry-run           Show what would be done, don't run git commands
  -a, --all               Add all changed files (git add -A). If not set, pass file paths.
  -h, --help              Show this help and exit

Examples:
  $prog_name --init -u https://github.com/user/repo.git -m "initial commit" --push README.md
  $prog_name -a -m "update" # add all, commit and push
EOF
}

# Defaults
commit_msg="auto commit"
branch="main"
remote_name="origin"
remote_url=""
dry_run=0
init_repo=0
push_after=1
add_all=0
force_push=0

# Parse args
args=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    -m|--message)
      commit_msg="$2"; shift 2;;
    -b|--branch)
      branch="$2"; shift 2;;
    -r|--remote)
      remote_name="$2"; shift 2;;
    -u|--url)
      remote_url="$2"; shift 2;;
    --init)
      init_repo=1; shift;;
    --push)
      push_after=1; shift;;
    --no-push)
      push_after=0; shift;;
    --force)
      force_push=1; shift;;
    -n|--dry-run)
      dry_run=1; shift;;
    -a|--all)
      add_all=1; shift;;
    -h|--help)
      usage; exit 0;;
    --)
      shift; break;;
    --*)
      echo "Unknown option: $1" >&2; usage; exit 1;;
    *)
      args+=("$1"); shift;;
  esac
done

# If any remaining args, append
if [[ ${#args[@]} -gt 0 ]]; then
  paths=("${args[@]}")
else
  paths=()
fi

run() {
  if [[ $dry_run -eq 1 ]]; then
    echo "DRY-RUN: $*"
  else
    echo "+ $*"
    "$@"
  fi
}

# Check for git
if ! command -v git >/dev/null 2>&1; then
  echo "git not found in PATH" >&2
  exit 2
fi

# Optionally init repo if requested or if .git missing
if [[ $init_repo -eq 1 || ! -d .git ]]; then
  echo "Repository not initialized. Running git init..."
  run git init
fi

# Set remote if URL provided
if [[ -n "$remote_url" ]]; then
  # If remote exists, set-url, else add
  if git remote get-url "$remote_name" >/dev/null 2>&1; then
    echo "Setting remote '$remote_name' URL to $remote_url"
    run git remote set-url "$remote_name" "$remote_url"
  else
    echo "Adding remote '$remote_name' -> $remote_url"
    run git remote add "$remote_name" "$remote_url"
  fi
fi

# Ensure branch exists locally
if ! git rev-parse --verify "$branch" >/dev/null 2>&1; then
  echo "Creating and switching to branch '$branch'"
  run git checkout -b "$branch"
else
  echo "Switching to branch '$branch'"
  run git checkout "$branch"
fi

# Add files
if [[ $add_all -eq 1 ]]; then
  run git add -A
elif [[ ${#paths[@]} -gt 0 ]]; then
  run git add -- "${paths[@]}"
else
  # default: add README.md if exists, else add all
  if [[ -f README.md ]]; then
    run git add README.md
  else
    run git add -A
  fi
fi

# Commit
# Check if there is anything to commit
if git diff --staged --quiet; then
  echo "No staged changes to commit."
else
  run git commit -m "$commit_msg"
fi

# Push
if [[ $push_after -eq 1 ]]; then
  push_cmd=(git push -u "$remote_name" "$branch")
  if [[ $force_push -eq 1 ]]; then
    push_cmd+=(--force)
  fi
  run "${push_cmd[@]}"
else
  echo "Push skipped (--no-push)"
fi

echo "Done."
