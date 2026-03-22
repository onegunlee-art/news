#!/bin/bash
set -e

cp /tmp/backup-mysql.sh /usr/local/bin/backup-mysql.sh
chmod +x /usr/local/bin/backup-mysql.sh

(crontab -l 2>/dev/null | grep -v backup-mysql; echo "0 3 * * * /usr/local/bin/backup-mysql.sh >> /var/log/mysql-backup.log 2>&1") | crontab -

/usr/local/bin/backup-mysql.sh
echo "Backup test completed"

echo "=== Crontab ==="
crontab -l

echo ""
echo "=== Backup files ==="
ls -lh /var/backups/mysql/
