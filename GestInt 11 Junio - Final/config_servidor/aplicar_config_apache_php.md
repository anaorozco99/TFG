# Ocultar versiones Apache y PHP — cambios en XAMPP

## ¿Por qué?
Las páginas de error (403 Forbidden, 404 Not Found) muestran por defecto la versión exacta
de Apache y PHP. Un atacante puede buscar CVEs específicos de esas versiones.
Ocultarlos no elimina vulnerabilidades, pero dificulta el reconocimiento inicial.

---

## 1. httpd.conf — ocultar versión de Apache

**Ruta en XAMPP:** `C:\xampp\apache\conf\httpd.conf`

Busca las líneas (pueden estar en distintos lugares del archivo):
```
ServerTokens Full
ServerSignature On
```

Cámbialas a:
```
ServerTokens Prod
ServerSignature Off
```

- `ServerTokens Prod` → la cabecera `Server:` solo dice `Apache`, sin versión ni SO.
- `ServerSignature Off` → elimina la firma del pie en páginas de error generadas por Apache.

Si no encuentras esas líneas, **añádelas al final del archivo**.

---

## 2. php.ini — ocultar versión de PHP

**Ruta en XAMPP:** `C:\xampp\php\php.ini`

Busca:
```
expose_php = On
```

Cámbiala a:
```
expose_php = Off
```

Esto elimina la cabecera `X-Powered-By: PHP/8.2.x` de todas las respuestas HTTP.

---

## 3. Reiniciar Apache en XAMPP

Después de guardar los cambios, reinicia Apache desde el panel de control de XAMPP
(Stop → Start en la fila de Apache) para que los cambios surtan efecto.

---

## 4. Verificar

Desde el navegador, abre las DevTools (F12) → pestaña Red → recarga una página de GestInt.
En los encabezados de respuesta ya no debe aparecer la versión de Apache ni la cabecera X-Powered-By.

También puedes provocar un 403 accediendo a `http://gestint.local/includes/db.php`
y comprobar que el pie de la página de error no muestra la versión de Apache.
