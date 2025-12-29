# How-To: Prune Sessions, Cache, and Logs (Production Maintenance)

Ishmael stores sessions and cache files on disk. In long-running environments these folders can grow quickly unless they are pruned regularly.

This guide explains the provided PowerShell script, how to run it safely, and how to automate it in production.

## What this does
- Removes old files from:
  - storage/sessions
  - storage/cache
  - storage/logs (optional)
- Safe by default: supports PowerShell WhatIf (dry-run) so you can verify before deleting.

## Where is the script?
- Path (inside IshmaelPHP-Core repo):
  - IshmaelPHP-Core/tools/maintenance/prune-storage.ps1
- Note: The Tools and Docs folders are excluded from Composer/ZIP exports via .gitattributes, so the script will not be shipped when IshmaelPHP-Core is installed as a dependency.

## Usage examples
Run from the repository root or from the IshmaelPHP-Core folder. Adjust days to match your policies.

- Dry run, keep sessions 3 days, cache 1 day, logs 14 days (no logs deletion without -IncludeLogs):

  pwsh tools/maintenance/prune-storage.ps1 -RootPath . -SessionDays 3 -CacheDays 1 -LogsDays 14 -WhatIf

- Typical nightly prune including logs (retain 7 days):

  pwsh tools/maintenance/prune-storage.ps1 -RootPath . -SessionDays 7 -CacheDays 2 -LogsDays 7 -IncludeLogs

Parameters
- RootPath: Base path to resolve storage folders (default is current directory)
- SessionDays: Remove session files older than N days (default 7)
- CacheDays: Remove cache files older than N days (default 2; set 0 to skip cache)
- LogsDays: Remove logs older than N days when -IncludeLogs is set (default 14)
- IncludeLogs: Also prune logs
- WhatIf: Dry-run mode (recommended first)

## Automate in production
Run the cleanup during off-peak hours with an account that has permission to delete files in the storage folders.

### Windows (Task Scheduler)
1) Open Task Scheduler -> Create Basic Task.
2) Trigger: Daily (pick a quiet time, e.g., 02:30).
3) Action: Start a Program.
   - Program/script: pwsh
   - Add arguments:
     -File "C:\\path\\to\\IshmaelPHP-Core\\tools\\maintenance\\prune-storage.ps1" -RootPath "C:\\path\\to\\IshmaelPHP-Core" -SessionDays 7 -CacheDays 2 -LogsDays 7 -IncludeLogs
   - Start in: C:\\path\\to\\IshmaelPHP-Core
4) Check "Run whether user is logged on or not" and "Run with highest privileges" if needed.
5) Test with -WhatIf first to confirm the targets.

### Linux (cron)
Although the script is PowerShell, Linux servers can run it with PowerShell Core (pwsh). Alternatively, you can prune using native tools:

- Sessions older than 7 days:
  find /var/www/ish/IshmaelPHP-Core/storage/sessions -type f -mtime +7 -delete

- Cache older than 2 days (recursive):
  find /var/www/ish/IshmaelPHP-Core/storage/cache -type f -mtime +2 -delete

- Logs older than 7 days:
  find /var/www/ish/IshmaelPHP-Core/storage/logs -type f -mtime +7 -delete

Add entries to root or app user's crontab to run nightly.

## Operational tips
- Always start with a dry run: add -WhatIf (PowerShell) or use -print with find on Linux.
- In clustered deployments, schedule cleanup on one node or shard responsibility to avoid races.
- Ensure your SessionDays aligns with your session lifetime to avoid logging users out.

## Related
- Ishmael CLI cache clear (does not clear sessions):
  php bin/ish cache:clear --stats
