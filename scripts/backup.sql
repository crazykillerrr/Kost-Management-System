-- Backup Database Script
-- This script creates a backup of the kost_management database

-- Create backup directory if not exists
-- Note: This would typically be run from command line or scheduled task

-- Backup command (to be run from command line):
-- mysqldump -u root -p kost_management > backup_kost_$(date +%Y%m%d_%H%M%S).sql

-- Restore command:
-- mysql -u root -p kost_management < backup_file.sql

-- For automated backup, create a batch file or shell script:

-- Windows batch file (backup.bat):
-- @echo off
-- set TIMESTAMP=%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%
-- set TIMESTAMP=%TIMESTAMP: =0%
-- mysqldump -u root -p kost_management > "backups/backup_kost_%TIMESTAMP%.sql"
-- echo Backup completed: backup_kost_%TIMESTAMP%.sql

-- Linux shell script (backup.sh):
-- #!/bin/bash
-- TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
-- mysqldump -u root -p kost_management > "backups/backup_kost_$TIMESTAMP.sql"
-- echo "Backup completed: backup_kost_$TIMESTAMP.sql"

-- Clean old backups (keep only last 30 days)
-- find backups/ -name "backup_kost_*.sql" -mtime +30 -delete

-- Schedule this script to run daily using cron (Linux) or Task Scheduler (Windows)
-- Cron example (run daily at 2 AM):
-- 0 2 * * * /path/to/backup.sh

SELECT 'Backup script template created. Please set up automated backup using system scheduler.' as message;
