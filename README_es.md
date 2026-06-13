# Buzón del curso (mod_coursemail)

**Sitúa la comunicación profesor-alumno donde debe estar: en el interior del curso, como una actividad didáctica más.**

> 🇬🇧 English version: [README.md](README.md).

Un curso de Moodle es una *secuencia ordenada* de actividades y recursos: un tema,
una lectura, un vídeo, una tarea, un examen. Pero la comunicación personal con el
alumnado casi siempre se escapa de esa secuencia hacia el correo — donde no sabes
si el mensaje se ha leído, el alumno tiene que salir del curso para verlo,
ignorarlo no tiene consecuencias y nada queda registrado en los informes del curso.

**coursemail** resuelve eso. Es un buzón de mensajería que es, a la vez, un
**servicio de correo** *y* una **actividad de Moodle**:

- Como **servicio de correo** ofrece lo que esperas: conversaciones en hilos,
  carpetas de Recibidos / Enviados / Borradores / Destacados, editor HTML y envío a
  un alumno, a un grupo o a toda la clase — todo **dentro del LMS**, sin servidor de
  correo externo y sin que un tercero custodie los datos de tus alumnos.
- Como **actividad de Moodle** aporta **finalización de actividad** (por lectura,
  por respuesta o marcada manualmente alumno a alumno), **seguimiento** en los
  informes del curso y la capacidad de convertirse en un **punto de control del
  avance**: combinado con la restricción de acceso nativa de Moodle, el buzón puede
  actuar como compuerta — *"no puedes pasar al siguiente tema hasta que hayas leído
  (y respondido) este mensaje"*.

> Compatible con **Moodle 4.1 o superior**.

## ¿Por qué coursemail y no la mensajería estándar de Moodle o local_mail?

| | Mensajería de Moodle (core) | local_mail | **coursemail** |
|---|:---:|:---:|:---:|
| Ámbito | Todo el sitio | Por curso | **Por actividad** |
| Conversaciones en hilos | No | Sí | Sí |
| Carpetas (Recibidos / Enviados / Borradores / Destacados) | Limitado | Sí | Sí |
| Adjuntos | Sí | Sí | Sí |
| Notificaciones por correo / push | Sí | Sí | No (v1) |
| **Finalización de actividad** | No | No | **Sí** |
| **Acuse de lectura que completa actividad** | No | No | **Sí** |
| **Respuesta obligatoria que completa actividad** | No | No | **Sí** |
| **Marcado manual alumno por alumno** | No | No | **Sí** |
| **Compuerta de avance** (restricción de acceso) | No | No | **Sí** |
| Múltiples instancias independientes por curso | No | No | **Sí** |
| Configuración individual por instancia | No | No | **Sí** |
| RGPD (exportación + borrado) | Sí | Sí | Sí |
| Backup / restauración con el curso | Sí | No¹ | Sí |

¹ `local_mail` es un plugin de tipo `local`, por lo que sus datos no viajan con el
backup estándar de Moodle.

**En resumen:**

- **Mensajería estándar de Moodle** — útil para comunicación informal entre
  usuarios del sitio, pero vive fuera del contexto del curso y no genera ningún
  registro de finalización. No se puede usar como compuerta.
- **local_mail** — un excelente buzón de curso con adjuntos y notificaciones, pero
  es un plugin de tipo `local`, no una actividad. Al no integrarse con el motor de
  finalización de Moodle, no puede condicionar el avance ni aparece en los informes
  de finalización. Tampoco se respalda con el curso.
- **coursemail** — elige este plugin cuando la comunicación deba ser un **punto de
  control del aprendizaje**: "el alumno no puede continuar hasta que haya leído y
  respondido". A cambio, en v1 no tiene notificaciones por correo/push.

## Cómo se usa

El mismo plugin admite tres niveles de control — tú eliges para cada actividad:

1. **Acompañar** — un buzón abierto en el curso, sin bloqueo. Ya mejor que el correo
   porque vive en contexto y deja registro.
2. **Validar** — activa la finalización por *Contestado* y/o marca una conversación
   como resuelta a mano, alumno a alumno, para un seguimiento cualitativo.
3. **Bloquear** — activa la finalización por *Leído* / *Contestado* y añade una
   **restricción de acceso** en la actividad siguiente, de modo que el buzón
   condicione el avance.

Flujo de trabajo típico:

1. Un profesor (cualquiera con `mod/coursemail:send`) añade una actividad **Buzón
   del curso** y, en sus ajustes, elige las condiciones de **finalización**
   (*visto*, *leído*, *contestado*) y si las conversaciones requieren respuesta.
2. El profesorado inicia conversaciones hacia individuos, un grupo o toda la clase;
   el alumnado lee y responde desde el mismo buzón y también puede abrir hilos
   dirigidos al profesorado.
3. Los mensajes del profesorado se controlan con acuse de lectura; la finalización
   se actualiza automáticamente (y se reabre si cambian las condiciones, según el
   recálculo estándar de Moodle).
4. Para condicionar el curso, añade en la actividad siguiente *Restringir acceso →
   Finalización de actividad → El Buzón del curso debe estar marcado como
   completado*.

## Funcionalidades

- **Conversaciones en hilos** (no mensajes planos).
- **Destinatarios:** usuarios individuales, toda la clase o grupos/agrupamientos
  (capacidad de profesor). El alumnado inicia hilos dirigidos al profesorado.
- **Carpetas:** Recibidos, Enviados, Borradores y Destacados, con paginación
  "Cargar más".
- **Borradores** que se pueden editar y enviar más tarde.
- **Finalización de actividad** (además de la regla "ver" del núcleo):
  - *Leído*: el alumno ha leído todos los mensajes del profesorado que recibió.
  - *Contestado*: lo anterior y, en cada conversación marcada como que requiere
    respuesta, ha respondido al menos una vez.
  - *Marcado manualmente por el profesor*, por alumno, en las conversaciones que lo
    usen.
- **Cuerpo de mensaje en HTML** con soporte de **adjuntos** (configurable: tamaño
  máximo y número máximo por mensaje desde los ajustes del sitio).
- **Privacidad (RGPD):** proveedor completo — exportación (incluidos borradores) y
  borrado.
- **Copia de seguridad / restauración:** todos los datos y los ficheros de la
  descripción de la actividad.
- **Reinicio del curso:** opción para borrar todas las conversaciones y mensajes.
- **App móvil:** soporte mínimo (muestra la descripción y un enlace para abrir el
  buzón en el navegador).

## Requisitos

- **Moodle 4.1 o superior** (`$plugin->requires = 2022112800`).
- **PHP 7.4+** (el código se mantiene dentro del juego de características de 7.4).

## Instalación

1. Copia esta carpeta en `mod/coursemail` dentro de tu instalación de Moodle (la
   carpeta **debe** llamarse `coursemail`).
2. Ve a *Administración del sitio → Notificaciones* (o ejecuta
   `php admin/cli/upgrade.php`) para instalar.

## Ajustes

*Administración del sitio → Plugins → Módulos de actividad → Buzón del curso*:

- **Elementos por página** (`mod_coursemail/perpage`, por defecto 50): el tamaño de
  página de la paginación "Cargar más" de las carpetas.

## Capacidades

| Capacidad | Roles por defecto | Para qué sirve |
|---|---|---|
| `mod/coursemail:addinstance` | editingteacher | Añadir la actividad a un curso. |
| `mod/coursemail:view` | student, teacher | Ver el buzón. |
| `mod/coursemail:send` | teacher, manager | Enviar a individuos/clase/grupos. Estos mensajes cuentan para la finalización. |
| `mod/coursemail:reply` | student, teacher | Iniciar un hilo hacia el profesorado y responder. |
| `mod/coursemail:viewall` | manager | Supervisar todos los buzones. |

## Desarrollo

- Frontend: plantillas Mustache + módulos AMD nativos (sin frameworks externos).
  Tras editar `amd/src/*`, recompila `amd/build/*` con `grunt amd`.
- Estilo de código: estándar de Moodle (`moodle-cs`).
- Tests: PHPUnit en `tests/`. Features de Behat en `tests/behat/` (requieren un
  entorno Behat con JavaScript).

## Licencia

GNU GPL v3 o posterior. Véase <https://www.gnu.org/licenses/>.
