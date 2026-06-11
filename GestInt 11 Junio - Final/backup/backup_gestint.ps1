# backup_gestint.ps1
# hace el volcado de la bd, lo cifra con aes y lo guarda en local
# se ejecuta automaticamente con el programador de tareas de windows

# --- configuracion ---
$mysqldump  = "C:\xampp\mysql\bin\mysqldump.exe"
$db         = "gestint_db"
$usuario    = "admin_gestint"
$contrasena = "TU_PASSWORD_AQUI"        # cambia esto
$carpeta    = "C:\backups\gestint"      # aqui se guardan los backups
$clave_aes  = "CambiaEstaClave32Chars!!"  # exactamente 32 caracteres, guardala aparte
$dias       = 7                         # cuantos dias se conservan los backups

# crear carpeta si no existe
if (-not (Test-Path $carpeta)) {
    New-Item -ItemType Directory -Path $carpeta -Force | Out-Null
}

# nombre del archivo con la fecha y hora
$fecha    = Get-Date -Format "yyyy-MM-dd_HH-mm"
$sql      = "$carpeta\gestint_$fecha.sql"
$cifrado  = "$sql.enc"
$log      = "$carpeta\backup.log"

function Anotar($msg) {
    $linea = "[$(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')] $msg"
    Write-Host $linea
    Add-Content $log $linea
}

Anotar "-- inicio backup --"

# paso 1: volcado de la base de datos
Anotar "volcando base de datos..."
$env:MYSQL_PWD = $contrasena
& $mysqldump --host=localhost --user=$usuario --single-transaction --add-drop-table $db |
    Out-File -FilePath $sql -Encoding UTF8
$env:MYSQL_PWD = $null

if (-not (Test-Path $sql)) {
    Anotar "ERROR: el volcado ha fallado, abortando"
    exit 1
}

Anotar "volcado ok — $([math]::Round((Get-Item $sql).Length / 1KB, 1)) KB"

# paso 2: cifrar con aes-256 usando .net (no hace falta instalar nada)
Anotar "cifrando..."

$bytes_clave = [System.Text.Encoding]::UTF8.GetBytes($clave_aes.PadRight(32).Substring(0,32))
$iv = New-Object byte[] 16
[System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($iv)

$aes         = [System.Security.Cryptography.Aes]::Create()
$aes.Key     = $bytes_clave
$aes.IV      = $iv
$aes.Mode    = [System.Security.Cryptography.CipherMode]::CBC
$aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7

$datos    = [System.IO.File]::ReadAllBytes($sql)
$cifrados = $aes.CreateEncryptor().TransformFinalBlock($datos, 0, $datos.Length)

# guardar: primero el iv (16 bytes) y luego los datos cifrados
$stream = [System.IO.File]::Create($cifrado)
$stream.Write($iv, 0, 16)
$stream.Write($cifrados, 0, $cifrados.Length)
$stream.Close()

Anotar "cifrado ok — $cifrado"

# borrar el sql en claro
Remove-Item $sql -Force

# paso 3: borrar backups con mas de $dias dias
$antiguos = Get-ChildItem $carpeta -Filter "*.sql.enc" |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$dias) }

foreach ($f in $antiguos) {
    Remove-Item $f.FullName -Force
    Anotar "backup antiguo borrado: $($f.Name)"
}

$total = (Get-ChildItem $carpeta -Filter "*.sql.enc").Count
Anotar "backup terminado — $total backups guardados"
Anotar "-- fin --"
