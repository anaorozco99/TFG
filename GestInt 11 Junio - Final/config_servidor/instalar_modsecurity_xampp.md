# Instalar ModSecurity en XAMPP (Windows Server 2022)

ModSecurity es un WAF (Web Application Firewall) que se integra como módulo de Apache.
Analiza cada petición HTTP y la bloquea si coincide con patrones de ataque conocidos
(SQL injection, XSS, LFI, etc.) usando el ruleset OWASP Core Rule Set (CRS).

---

## 1. Descargar ModSecurity para Apache en Windows

ModSecurity 2.9 para Apache en Windows se distribuye como DLL precompilada.

1. Descarga desde el repositorio oficial de XAMPP/Apache Lounge:
   https://www.apachelounge.com/download/
   Busca `mod_security2` en la sección de módulos para tu versión de Apache (VC17 64-bit).

2. Extrae el ZIP. Obtendrás:
   - `mod_security2.so`
   - `yajl.dll`
   - `libxml2.dll`

3. Copia los tres archivos a `C:\xampp\apache\modules\`

---

## 2. Activar el módulo en httpd.conf

Abre `C:\xampp\apache\conf\httpd.conf` y añade al final de la sección de módulos (junto a los demás LoadModule):

```apache
LoadModule security2_module modules/mod_security2.so
```

---

## 3. Crear el archivo de configuración de ModSecurity

Crea el archivo `C:\xampp\apache\conf\extra\modsecurity.conf` con este contenido mínimo:

```apache
# ModSecurity — configuracion basica GestInt
SecRuleEngine On

# Limite de tamaño de cuerpo de peticion (10 MB)
SecRequestBodyLimit 10485760
SecRequestBodyNoFilesLimit 131072

# Activar inspeccion del cuerpo de peticion y respuesta
SecRequestBodyAccess On
SecResponseBodyAccess Off

# Log de auditoría
SecAuditEngine RelevantOnly
SecAuditLog "C:/xampp/apache/logs/modsec_audit.log"
SecAuditLogParts ABIJDEFHZ
SecAuditLogType Serial

# Directorio temporal para datos de ModSecurity
SecTmpDir "C:/xampp/tmp"
SecDataDir "C:/xampp/tmp"

# Evitar revelar que ModSecurity esta instalado
SecServerSignature "Apache"

# Incluir el OWASP Core Rule Set (ver paso 4)
Include "C:/xampp/apache/conf/extra/crs/crs-setup.conf"
Include "C:/xampp/apache/conf/extra/crs/rules/*.conf"
```

---

## 4. Instalar OWASP Core Rule Set (CRS)

El CRS es el conjunto de reglas estándar de OWASP que detecta los ataques más comunes.

1. Descarga la última versión desde:
   https://github.com/coreruleset/coreruleset/releases

2. Extrae el ZIP y renombra la carpeta como `crs` dentro de:
   `C:\xampp\apache\conf\extra\crs\`

3. Dentro de esa carpeta verás `crs-setup.conf.example`:
   Cópialo y renómbralo a `crs-setup.conf`.

4. Abre `crs-setup.conf` y verifica que la línea de modo sea:
   ```
   SecDefaultAction "phase:1,log,auditlog,pass"
   ```
   Para empezar en modo **DetectionOnly** (solo registra, no bloquea) cambia a:
   ```
   SecRuleEngine DetectionOnly
   ```
   Una vez comprobado que no hay falsos positivos, activa el bloqueo:
   ```
   SecRuleEngine On
   ```

---

## 5. Incluir modsecurity.conf desde httpd.conf

Al final de `C:\xampp\apache\conf\httpd.conf` añade:

```apache
Include conf/extra/modsecurity.conf
```

---

## 6. Reiniciar Apache y verificar

1. Reinicia Apache desde el panel de XAMPP.
2. Si Apache no arranca, revisa el log de errores:
   `C:\xampp\apache\logs\error.log`
3. Para comprobar que ModSecurity está activo, intenta una petición con un patrón de SQL injection:
   ```
   http://gestint.local/login.php?user=' OR '1'='1
   ```
   Debe devolver un **403 Forbidden** y quedar registrado en `modsec_audit.log`.

---

## 7. Excluir rutas que generen falsos positivos

Si ModSecurity bloquea peticiones legítimas (por ejemplo, el editor de texto o subida de archivos),
añade excepciones en `modsecurity.conf`:

```apache
# Excluir la subida de justificantes del CRS (archivos PDF/JPG legítimos)
<LocationMatch "^/pages/perfil\.php">
    SecRuleRemoveById 200002 200003
</LocationMatch>
```

---

## Resumen del efecto en GestInt

| Ataque | Sin ModSecurity | Con ModSecurity |
|--------|----------------|-----------------|
| SQL injection en URL | Bloqueado por prepared statements | Bloqueado también por WAF (doble capa) |
| XSS en parámetros | Bloqueado por htmlspecialchars | Bloqueado también por reglas CRS |
| LFI (../../etc/passwd) | No protegido explícitamente | Bloqueado por CRS |
| Escaneo de vulnerabilidades | Visible en logs | Bloqueado y registrado |
