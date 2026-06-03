# GestInt — Sistema de Gestión Interna

Aplicación web de gestión interna desarrollada como Proyecto Integrado de 2º CFGS ASIR.

Implementada para **Dunder Mifflin Paper Company — Scranton Branch** como empresa de ejemplo.

---

## ¿Qué hace?

- Gestión de empleados (altas, bajas, edición)
- Control de inventario y stock
- Sistema de pedidos de material con seguimiento de entregas
- Gestión de usuarios con 4 roles diferenciados
- Registro de accesos al sistema

## Roles

| Rol | Acceso |

| Administrador | Acceso total |
| Resp. RRHH | Gestión de empleados y usuarios |
| Resp. Almacén | Inventario y pedidos |
| Empleado | Solo lectura |

## Stack tecnológico

- **Servidor:** Windows Server 2022 + IIS
- **Virtualización:** VirtualBox + pfSense CE
- **Backend:** PHP 8.2
- **Base de datos:** MySQL (WAMP)
- **Frontend:** HTML5, CSS3, JavaScript

## Instalación local (desarrollo)

1. Instalar XAMPP
2. Copiar la carpeta `GestInt` en `C:\xampp\htdocs\`
3. Crear base de datos `gestint` en phpMyAdmin
4. Acceder a `http://localhost/GestInt/instalar.php`
5. Entrar en `http://localhost/GestInt/login.php`

## Credenciales de prueba

| Usuario | Contraseña | Rol |
| michael | Michael1234 | Administrador |
| pam | Pam1234!! | Resp. RRHH |
| darryl | Darryl1234 | Resp. Almacén |
| jim | Jim1234!! | Empleado |

## Licencia

MIT License — ver archivo `LICENSE`
