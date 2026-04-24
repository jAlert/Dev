#!/usr/bin/env bash
# PRMS Backup Script
# Creates a timestamped zip containing project files + MySQL dump
# Usage: bash backup.sh
# Output: D:/Dev/backups/prms-backup-YYYYMMDD_HHMMSS.zip

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="prms-backup-${TIMESTAMP}"
BACKUP_DIR="${SCRIPT_DIR}/../backups"
SQL_FILE="${SCRIPT_DIR}/database_${TIMESTAMP}.sql"

cd "${SCRIPT_DIR}"

# ── Load DB credentials from .env ──────────────────────────────────────────
if [ ! -f ".env" ]; then
  echo "ERROR: .env file not found."
  exit 1
fi

DB_DATABASE=$(grep -E '^DB_DATABASE=' .env | cut -d'=' -f2)
DB_USERNAME=$(grep -E '^DB_USERNAME=' .env | cut -d'=' -f2)
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d'=' -f2)

echo "======================================"
echo " PRMS Backup — ${TIMESTAMP}"
echo "======================================"

# ── Check Docker MySQL is running ──────────────────────────────────────────
if ! docker compose ps --services --filter "status=running" 2>/dev/null | grep -q "mysql"; then
  echo "ERROR: MySQL container is not running. Start it first:"
  echo "  docker compose up -d mysql"
  exit 1
fi

mkdir -p "${BACKUP_DIR}"

# ── Dump database ──────────────────────────────────────────────────────────
echo ""
echo "[1/3] Dumping database '${DB_DATABASE}'..."
docker compose exec -T mysql mysqldump \
  --no-tablespaces \
  -u "${DB_USERNAME}" \
  -p"${DB_PASSWORD}" \
  "${DB_DATABASE}" > "${SQL_FILE}"

SQL_SIZE=$(du -sh "${SQL_FILE}" | cut -f1)
echo "      Done. (${SQL_SIZE})"

# ── Build zip via PowerShell ZipFile API ──────────────────────────────────
echo ""
echo "[2/3] Archiving project files..."

WIN_SRC=$(cygpath -w "${SCRIPT_DIR}")
WIN_SQL=$(cygpath -w "${SQL_FILE}")
WIN_OUT=$(cygpath -w "${BACKUP_DIR}/${BACKUP_NAME}.zip")

powershell -NoProfile -Command "
Add-Type -Assembly 'System.IO.Compression.FileSystem'

\$src     = '${WIN_SRC}'
\$sqlFile = '${WIN_SQL}'
\$dest    = '${WIN_OUT}'

# Directories to exclude (relative to project root, using backslash)
\$excludeDirs = @(
    'vendor', 'node_modules', '.git',
    'public\build', 'public\storage',
    'storage\logs',
    'storage\framework\cache',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\framework\testing'
) | ForEach-Object { (Join-Path \$src \$_).ToLower() + '\' }

if (Test-Path \$dest) { Remove-Item \$dest -Force }
\$zip = [System.IO.Compression.ZipFile]::Open(\$dest, 'Create')

\$files = Get-ChildItem \$src -Recurse -File -ErrorAction SilentlyContinue
\$count = 0
foreach (\$file in \$files) {
    \$lower = \$file.FullName.ToLower() + '\'
    \$skip  = \$false
    foreach (\$excl in \$excludeDirs) {
        if (\$lower.StartsWith(\$excl)) { \$skip = \$true; break }
    }
    if (\$skip) { continue }
    if (\$file.FullName -like '*.sql') { continue }  # skip any loose sql files

    \$rel = \$file.FullName.Substring(\$src.Length).TrimStart('\')
    try {
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            \$zip, \$file.FullName, \$rel, 'Optimal'
        ) | Out-Null
        \$count++
    } catch {
        Write-Warning \"Skipped: \$(\$file.FullName)\"
    }
}

# Add database dump as database.sql at zip root
[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
    \$zip, \$sqlFile, 'database.sql', 'Optimal'
) | Out-Null

\$zip.Dispose()
Write-Host \"      Done. (\$count files + database.sql)\"
"

# ── Cleanup SQL dump ───────────────────────────────────────────────────────
rm -f "${SQL_FILE}"

ZIP_SIZE=$(du -sh "${BACKUP_DIR}/${BACKUP_NAME}.zip" | cut -f1)

echo ""
echo "[3/3] Backup complete."
echo ""
echo "  File : ${BACKUP_DIR}/${BACKUP_NAME}.zip"
echo "  Size : ${ZIP_SIZE}"
echo ""
