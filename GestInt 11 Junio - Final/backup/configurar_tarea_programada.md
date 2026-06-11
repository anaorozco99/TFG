# Configurar backup automático de GestInt

El script `backup_gestint.ps1` hace un volcado de la BD, lo cifra con AES-256
y lo guarda en local. No necesita instalar nada extra, usa `mysqldump` que ya viene
con XAMPP y el cifrado .NET que trae Windows.

---

## 1. Configurar el script

Abre `backup_gestint.ps1` y cambia estas variables al principio:

- `$contrasena` — password del usuario `admin_gestint` en MySQL
- `$carpeta` — donde se van a guardar los backups (ej: `C:\backups\gestint`)
- `$clave_aes` — exactamente 32 caracteres, guárdala aparte porque la necesitas para descifrar

---

## 2. Probarlo a mano

Abre PowerShell como Administrador y ejecuta:

```powershell
powershell -ExecutionPolicy Bypass -File "C:\GestInt\backup\backup_gestint.ps1"
```

Tiene que aparecer el archivo `.enc` en la carpeta de backups y el log `backup.log`
con el resultado.

---

## 3. Crear la tarea programada

Copia y pega esto en PowerShell como Administrador (ajusta la ruta si hace falta):

```powershell
$ruta = "C:\GestInt\backup\backup_gestint.ps1"

$accion  = New-ScheduledTaskAction -Execute "powershell.exe" `
               -Argument "-NonInteractive -ExecutionPolicy Bypass -File `"$ruta`""
$trigger = New-ScheduledTaskTrigger -Daily -At "02:00AM"
$config  = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Hours 1) `
               -StartWhenAvailable

Register-ScheduledTask -TaskName "Backup GestInt BD" `
    -Action $accion -Trigger $trigger -Settings $config -RunLevel Highest
```

Para comprobar que se ha creado:
```powershell
Get-ScheduledTask -TaskName "Backup GestInt BD"
```

Para lanzarla manualmente:
```powershell
Start-ScheduledTask -TaskName "Backup GestInt BD"
```

---

## 4. Ver el log

```powershell
Get-Content "C:\backups\gestint\backup.log" -Tail 20
```

---

## 5. Descifrar un backup

Si necesitas recuperar la BD:

```powershell
$enc     = "C:\backups\gestint\gestint_FECHA.sql.enc"
$clave   = "CambiaEstaClave32Chars!!"   # la misma que en el script

$sql       = $enc -replace '\.enc$', ''
$key_bytes = [System.Text.Encoding]::UTF8.GetBytes($clave.PadRight(32).Substring(0,32))
$raw       = [System.IO.File]::ReadAllBytes($enc)
$iv        = $raw[0..15]
$cifrado   = $raw[16..($raw.Length - 1)]

$aes         = [System.Security.Cryptography.Aes]::Create()
$aes.Key     = $key_bytes
$aes.IV      = $iv
$aes.Mode    = [System.Security.Cryptography.CipherMode]::CBC
$aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7

$plano = $aes.CreateDecryptor().TransformFinalBlock($cifrado, 0, $cifrado.Length)
[System.IO.File]::WriteAllBytes($sql, $plano)
Write-Host "Descifrado en: $sql"
```

Luego importar en MySQL:
```
C:\xampp\mysql\bin\mysql.exe -u root -p gestint_db < gestint_FECHA.sql
```
