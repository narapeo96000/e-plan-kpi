<#
PowerShell helper to backup and import a MySQL dump file.
Prerequisites: `mysqldump` and `mysql` clients available in PATH.
Run on the production server or from a machine with access to the DB.
#>
param()

function Read-Default([string] $prompt, [string] $default) {
    $v = Read-Host "$prompt [$default]"
    if ([string]::IsNullOrWhiteSpace($v)) { return $default }
    return $v
}

$host = Read-Default 'MySQL host' 'localhost'
$user = Read-Host 'MySQL user'
$pass = Read-Host 'MySQL password' -AsSecureString
$plainPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($pass))
$db = Read-Host 'Database name'
$sqlFile = Read-Default 'Path to SQL file to import' '.\office_budget_edu_db.sql'

if (-not (Test-Path $sqlFile)) {
    Write-Error "SQL file not found: $sqlFile"
    exit 1
}

$timestamp = (Get-Date).ToString('yyyyMMddHHmmss')
$backupFile = "$db-backup-$timestamp.sql"

Write-Host "Creating backup of existing database to $backupFile..."
& mysqldump -h $host -u $user -p$plainPass $db > $backupFile
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Backup failed. Aborting.'
    exit 1
}

Write-Host "Importing $sqlFile into database $db..."
& mysql -h $host -u $user -p$plainPass $db < $sqlFile
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Import failed.'
    exit 1
}

Write-Host 'Import completed successfully.'
Write-Host "Backup saved as: $backupFile"
