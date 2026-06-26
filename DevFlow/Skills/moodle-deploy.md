---
name: moodle-deploy
description: |
  Deploy Moodle plugins from the current workspace to a local Moodle installation running in Docker on Orb (macOS VM manager). Use this skill whenever the user mentions deploying, installing, updating, or syncing a Moodle plugin to their local dev environment — even if they just say "deploy it", "push to Moodle", "install the plugin", "update my local Moodle", "sync to Orb", or "test it on my Moodle". Also trigger when the user asks to run Moodle CLI commands (upgrade, purge caches, init PHPUnit) on their local instance. This skill handles the full pipeline: auto-detecting the Orb VM and Docker container, copying files, running upgrade scripts, and purging caches.
---

# Moodle Deploy — Local Development Deployment

This skill deploys Moodle plugins from the workspace to a local Moodle instance running in Docker on Orb.

## How it works

The deployment script (`scripts/deploy.sh`) handles everything automatically:

1. **Discovers the Orb VM** — runs `orb list` and picks the running VM (asks if multiple)
2. **Finds the Moodle Docker container** — runs `docker ps` inside the VM and identifies the Moodle container by image name or mount paths
3. **Detects the Moodle webroot** — checks common paths (`/bitnami/moodle`, `/var/www/html`, `/var/www/html/moodle`, `/opt/moodle`) inside the container
4. **Copies plugin files** — uses `docker cp` to transfer the plugin directories
5. **Runs Moodle CLI** — executes `php admin/cli/upgrade.php --non-interactive` and `php admin/cli/purge_caches.php`

## Usage

### Quick deploy (most common)

Run the deploy script with the path to the plugin source directory:

```bash
bash <skill-path>/scripts/deploy.sh --source <plugin-source-dir>
```

The `<plugin-source-dir>` should contain the plugin in its Moodle-standard directory structure. For example, if the workspace has `mod/leitnerquiz/` and `blocks/leitnerquiz_progress/`, point `--source` at the parent directory containing both.

### Options

```
--source <path>       Source directory containing plugin(s) in Moodle layout (required)
--orb <name>          Orb VM name (skip auto-detection)
--container <name>    Docker container name (skip auto-detection)
--webroot <path>      Moodle webroot inside container (skip auto-detection)
--dry-run             Show what would happen without making changes
--skip-upgrade        Copy files but don't run upgrade.php
--skip-cache          Don't purge caches after deployment
--phpunit-init        Also run php admin/tool/phpunit/cli/init.php after deploy
--verbose             Show detailed output
```

### Examples

**Deploy everything from workspace:**
```bash
bash <skill-path>/scripts/deploy.sh \
  --source "/sessions/.../mnt/Moodle Quiz Plugin"
```

**Dry-run to see what would happen:**
```bash
bash <skill-path>/scripts/deploy.sh \
  --source "/sessions/.../mnt/Moodle Quiz Plugin" \
  --dry-run
```

**Deploy and initialize PHPUnit (for running tests):**
```bash
bash <skill-path>/scripts/deploy.sh \
  --source "/sessions/.../mnt/Moodle Quiz Plugin" \
  --phpunit-init
```

**Skip auto-detection if you already know your setup:**
```bash
bash <skill-path>/scripts/deploy.sh \
  --source "/sessions/.../mnt/Moodle Quiz Plugin" \
  --orb default \
  --container moodle-app-1 \
  --webroot /bitnami/moodle
```

## When deploying from this skill

When the user asks to deploy, follow these steps:

1. Identify which plugin directories exist in the workspace. Look for standard Moodle plugin layouts: `mod/`, `blocks/`, `local/`, `theme/`, etc.
2. Run the deploy script with `--dry-run` first so the user can confirm the plan
3. If the dry-run looks good, run the actual deployment
4. Report the result — especially any upgrade.php output or errors

If the deploy script can't auto-detect the environment (e.g., Orb is not running, no Docker container found), tell the user what's missing and suggest they start their environment first.

## Troubleshooting

**"No Orb VMs found"** — The user needs to start their Orb VM first (`orb create` or start it from the Orb app).

**"No Moodle container found"** — Docker might not be running inside the VM, or the Moodle stack isn't started. Suggest: `orb -m <vm> docker compose up -d` in the Moodle project directory.

**"Upgrade failed"** — Usually a PHP error. The script captures the output — show it to the user. Common causes: missing DB fields (need to check install.xml), PHP syntax errors, or dependency issues.

**Permission errors on docker cp** — Some Moodle Docker setups run as a non-root user. The script handles this by using `docker exec` to fix permissions after copying.

## Saving environment config

After the first successful deployment, the script saves the detected configuration to `~/.moodle-deploy.conf` so subsequent deploys skip auto-detection. The user can delete this file to force re-detection, or edit it to change settings.
