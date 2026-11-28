#!/usr/bin/env bash
set -euo pipefail

# Pemakaian:
#   MYSQL_CNF=/etc/petheal/backup.cnf BACKUP_DIR=/var/backups/petheal ./deploy/scripts/backup.sh
APP_DIR="${APP_DIR:-/var/www/petheal/current}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/petheal}"
MYSQL_CNF="${MYSQL_CNF:?Set MYSQL_CNF ke file kredensial mysqldump}"
DB_NAME="${DB_NAME:-pet_heal}"
STAMP="$(date +%Y%m%d_%H%M%S)"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

echo "==> Backup database"
mysqldump --defaults-extra-file="$MYSQL_CNF" \
  --single-transaction --routines --triggers --events \
  "$DB_NAME" | gzip > "$BACKUP_DIR/db_$STAMP.sql.gz"

echo "==> Backup storage"
tar -C "$APP_DIR" -czf "$BACKUP_DIR/storage_$STAMP.tar.gz" storage/app

echo "==> Hapus backup lebih dari $RETENTION_DAYS hari"
find "$BACKUP_DIR" -type f -mtime +"$RETENTION_DAYS" -delete

echo "Backup selesai di $BACKUP_DIR"
