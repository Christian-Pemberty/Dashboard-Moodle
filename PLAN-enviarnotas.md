# Plan de desarrollo — Módulo "Enviar notas por correo"

> Módulo nuevo dentro del plugin `block_mwa_dashboard` para que los docentes envíen a los
> estudiantes (correo + mensaje de Moodle) sus notas y resultados, con selección múltiple,
> vista previa y auditoría de cada envío.

## 1. Requisitos confirmados

| Pregunta | Decisión |
| --- | --- |
| Acceso | **Botón "Enviar notas" en el bloque del panel izquierdo**, que abre el módulo |
| Contenido del correo | **Nota global + desglose por actividad** (solo notas visibles) |
| Canales | **Correo + mensaje Moodle** en cada envío |
| Población | **Selección múltiple** de estudiantes (con "seleccionar todos") |

### No-objetivos (qué NO haremos)

- No crear un plugin nuevo ni tocar el dashboard monolítico (`dashboardapp.js`).
- No crear tablas nuevas: la auditoría reutiliza `block_mwa_dashboard_messages`.
- No incluir notas de otras personas en ningún correo (1 correo = 1 estudiante).
- No incluir items con `hidden = 1` (regla de visibilidad de Moodle).
- No usar IA para redactar el correo (opcional en el futuro).

## 2. Arquitectura de integración

```
Panel izquierdo (bloque, block_mwa_dashboard.php)
  └─ Botón "Enviar notas"  →  enviarnotas.php?course={courseid}
        └─ Página propia + AMD módulo enviarnotas.js
              ├─ Lee:  WS block_mwa_dashboard_get_grades            (EXISTENTE)
              ├─ Lee:  WS block_mwa_dashboard_get_grades_mail_preview (NUEVO)
              └─ Escribe: WS block_mwa_dashboard_send_grades_mail    (NUEVO)
                    ├─ 1 correo por estudiante (email_to_user)
                    ├─ 1 mensaje Moodle por estudiante (send_moodle_chat_only)
                    └─ 1 registro de auditoría por estudiante
                       (block_mwa_dashboard_messages, reason = 'Grades')
```

### Decisión de diseño documentada

- **Página propia `enviarnotas.php`** (misma convención que `configaikey.php` y `track.php`),
  en lugar de una pestaña dentro del dashboard. Razón: `dashboardapp.js`
  (≈101 KB) es un monolito; extenderlo obliga a recompilar y probar la UI completa del
  dashboard. La página propia mantiene el mismo UX (botón en el panel) y se desarrolla
  con riesgo mínimo.
- **Auditoría**: cada envío se registra en `block_mwa_dashboard_messages` con
  `send_type='both'`, `intervention_reason='Grades'`, y el texto exacto enviado.
  **No** se captura snapshot de intervención (los snapshots son para intervenciones
  pedagógicas; un correo de notas no es una intervención).
- **Fuente de datos**: `api::get_grades()` (filtra items `hidden=1`, calcula la nota
  global y por actividad, respeta exclusiones de tracking del curso y grupos).

## 3. Archivos por fase

| Archivo | Tipo | Fase |
| --- | --- | --- |
| `classes/external.php` | MOD | 1 |
| `db/services.php` | MOD | 1 |
| `version.php` | MOD | 1 |
| `enviarnotas.php` | NUEVO | 2 |
| `block_mwa_dashboard.php` | MOD | 2 |
| `lang/en/block_mwa_dashboard.php` | MOD | 3 |
| `lang/pt_br/block_mwa_dashboard.php` | MOD | 3 |
| `styles/gradesmail.css` | NUEVO | 3 |
| `amd/src/enviarnotas.js` | NUEVO | 3 |
| `amd/build/enviarnotas.min.js` | NUEVO | 3 |
| `tests/send_grades_mail_test.php` | NUEVO | 4 |
| `README.md` / `ESTRUCTURA.md` | MOD | 5 |

## 4. Fases de trabajo

### Fase 1 — Núcleo PHP (backend)  ✅ (código completo; criterios de runtime pendientes de verificación en Moodle de prueba)

**Objetivo:** los dos web services funcionan de extremo a extremo, aún sin UI.

Cambio | Detalle
| --- | --- |
| `classes/external.php` | Helper privado `build_grades_mail()` (sujet + HTML + texto plano a partir de `api::get_grades()`); WS `get_grades_mail_preview(courseid, userid, comment)` → `{subject, html, plain, studentname}`; WS `send_grades_mail(courseid, userids[], comment, groupid)` → `{success, sent, failed, results[]}` |
| `db/services.php` | Registrar `block_mwa_dashboard_get_grades_mail_preview` (read, ajax) y `block_mwa_dashboard_send_grades_mail` (write, ajax) |
| `version.php` | `$plugin->version = 2026082690` |

**Reglas del envío (implementación):**

- Capabilities requeridas: `block/mwa_dashboard:view` + `block/mwa_dashboard:manageinterventions`.
- Beneficiario: usuario existe y `is_enrolled()` en el curso; si no → error por estudiante (no aborta el lote).
- Correo: `email_to_user()` con versión HTML (tabla de notas) y texto plano (`html_to_text()`).
- Mensaje Moodle: `self::send_moodle_chat_only()` (misma función que `send_message`).
- Auditoría: 1 fila en `block_mwa_dashboard_messages` por estudiante, `status='sent'|'error'`,
  `send_type='both'`, `intervention_reason='Grades'`.
- Contenido del correo: saludo, curso y fecha, nota global (y máximo), tabla
  actividad–nota (dashes para pendientes), comentario opcional del docente, pie automático.

**Aceptación (cómo verificar al cerrar la fase):**

- [x] `php -l` pasa en `external.php`, `services.php`, `version.php` (verificado con PHP 8.3, 19-oct-2026).
- [ ] `send_grades_mail` con 2 estudiantes matriculados y 1 no matriculado →
      `sent=2, failed=1`; 2 filas nuevas en `block_mwa_dashboard_messages` con `status='sent'`.
- [ ] `get_grades_mail_preview` devuelve HTML que **no contiene** notas de otros estudiantes.
- [ ] Curso sin ítems de calificación → el correo dice "sin nota registrada" y el envío no falla.

---

### Fase 2 — Punto de acceso  ✅ (código completo; criterios de runtime pendientes de verificación en Moodle de prueba)

**Objetivo:** el docente llega al módulo desde el panel izquierdo.

Cambio | Detalle
| --- | --- |
| `enviarnotas.php` | NUEVO — página estilo `view.php`: `require_login`, capability `view`, layout, título, contenedor `<div id="mwaGradesMailRoot">` + assets registrados |
| `block_mwa_dashboard.php` | MOD — botón "Enviar notas" debajo de "Abrir dashboard", visible solo si el usuario tiene `manageinterventions` |
| `lang/en` + `lang/pt_br` | MOD — 28 strings de UI `gradesmail_*` (listados en §8) — agregados como adelantado de Fase 3 porque la página de la Fase 2 los usa |

**Aceptación:**

- [x] `php -l` pasa en `enviarnotas.php`, `block_mwa_dashboard.php` y los 2 archivos de `lang` (verificado con PHP 8.3, 19-oct-2026).
- [ ] En un curso, el profesor ve el bloque en la sidebar con 2 botones:
      "Abrir dashboard" y "Enviar notas".
- [ ] "Enviar notas" → `enviarnotas.php?course=X` carga sin errores (por ahora con
      esqueleto HTML + aviso "recargue JavaScript habilitado").
- [ ] Un usuario sin `manageinterventions` no ve el botón y la página le muestra
      "no tiene permiso".

**Detalles de implementación (Fase 2):**

- `enviarnotas.php`: `required_param('course', PARAM_INT)` + `require_login` +
  `require_capability` ×2 (`view` y `manageinterventions`) — un usuario sin permiso
  queda fuera por `required_capability_exception` (la página le muestra "no tiene permiso").
  Layout `embedded`, título/heading = `gradesmail_title`.
  Contenedor: `<div id="mwaGradesMailRoot" data-courseid="{id}" data-groupid="0">` con
  h1 + descripción + alerta `gradesmail_jsnotice` (aviso de JavaScript).
  **No** registra `js_call_amd` ni `styles/gradesmail.css`: ambos llegan en Fase 3
  (un `js_call_amd` al módulo no existente rompería la página en Fase 2).
- Botón del bloque (`block_mwa_dashboard.php::get_content()`): `$gradesmailbutton`
  (líneas 245-255) insertado **entre** el link "Abrir dashboard" y la línea
  `$gradesmailbutton .` (266), antes de `$capturecontrol`. Solo visible si
  `$courseid > 1` y `has_capability('block/mwa_dashboard:manageinterventions', ...)`.
  Estilo `btn btn-outline-primary` (distinto visualmente del `btn btn-primary` del dashboard).
- `version.php` **no** se tocó en Fase 2: ningún cambio de esquema/BD; la versión
  `2026082690` de Fase 1 cubre la misma ola de upgrade.

---

### Fase 3 — Frontend e idiomas  ✅ (código completo; criterios de runtime pendientes de verificación en Moodle de prueba)

**Objetivo:** la experiencia completa: lista, selección, preview, envío, resultados.

Cambio | Detalle
| --- | --- |
| `lang/en/…` + `lang/pt_br/…` | MOD — ~26 strings nuevos con prefijo `gradesmail_*` (UI, botones, estados) + `gradesmail_email_*` (cuerpo del correo: saludo, intro, global, cabeceras de tabla, "sin nota", comentario, pie) |
| `styles/gradesmail.css` | NUEVO — tarjeta/contenedor, tabla de estudiantes, badges de estado, área de preview |
| `amd/src/enviarnotas.js` | NUEVO — `init({courseid, groupid})`: carga `get_grades`, renderiza lista con checkbox + nota global; botones seleccionar/deseleccionar todos; textarea de comentario opcional; `Vista previa` (primer estudiante seleccionado) vía WS; `Enviar` con confirmación, estado "enviando", tabla de resultados por estudiante |
| `amd/build/enviarnotas.min.js` | NUEVO — versión minificada del mismo módulo |

**Aceptación:**

- [ ] Se ve la lista de estudiantes matriculados con su nota global.
- [ ] "Seleccionar todos" / "Deseleccionar todos" funcionan.
- [ ] "Vista previa" muestra AS-ES el correo que se va a enviar (misma función server
      que envía).
- [ ] "Enviar" muestra `Enviado`/`Erro` por estudiante y deshabilita el botón durante el envío.
- [ ] El texto se muestra en inglés y en portugués según el idioma de Moodle.
- [ ] En el historial de intervenciones del dashboard aparecen las notas enviadas (auditoría).

**Detalles de implementación (Fase 3):**

- `styles/gradesmail.css` — NUEVO. Clases `mwa-gm-*`: tarjetas, tablas, botones
  (`mwa-gm-btn-primary/-sec`), badges `mwa-gm-badge-sent/-error/-notenrolled`,
  área de preview (`mwa-gm-mail`), estilos de canales y errores. Registro:
  `$PAGE->requires->css(new moodle_url('/blocks/mwa_dashboard/styles/gradesmail.css'))`.
- `amd/src/enviarnotas.js` — NUEVO. `define(['core/webservices'])` (no depende de
  `dashboardstore`/`dashboardapp`, la página es independiente del monolito).
  `init({courseid, groupid, str})`: reemplaza el esqueleto de la Fase 2 dentro de
  `#mwaGradesMailRoot` (la alerta `gradesmail_jsnotice` queda visible solo sin JS).
  Flujo: `block_mwa_dashboard_get_grades` → `JSON.parse(res.grades)` → filas
  `__mwa_type__==='student'` → tabla checkbox + `Course total (Grade)`/`totalmax`
  (`nograde` si no hay nota); seleccionar/deseleccionar todos + contador
  `{count}`; textarea comentario; `Preview` del PRIMER seleccionado vía
  `block_mwa_dashboard_get_grades_mail_preview` (mismo helper server
  `build_grades_mail` del envío: la vista previa ES el correo final); `Enviar`
  con confirmación y deshabilitación del botón →
  `block_mwa_dashboard_send_grades_mail` → tabla de resultados con badge
  `sent|error|not_enrolled` y marca de canal email/moodle. Todo el texto visible
  sale de `str` (pasado por `get_string` desde la página); las respuestas del WS
  se escapan con `esc()` antes del `innerHTML`.
- `amd/build/enviarnotas.min.js` — NUEVO. Un solo renglón (9,384 bytes),
  generado con un minificador de uso puntual (sin Grunt): tokens
  string/comment/regex-aware (el `'` dentro de `/'/g` no rompe nada; los
  regex-literals se emiten verbatim).
- `enviarnotas.php` — MOD: registra `styles/gradesmail.css` +
  `js_call_amd('block_mwa_dashboard/enviarnotas', 'init', [{courseid, groupid:0, str:{…24 strings}}])`
  (los 24 strings `gradesmail_*` que el módulo consume; ya existían en `lang`
  desde Fase 2).
- `version.php` — SIN cambios (sin cambios de esquema; `2026082690` cubre).

**Verificación de Fase 3:**

- [x] `php -l` en `enviarnotas.php` — OK (PHP 8.3).
- [x] `node --check` en `amd/src/enviarnotas.js` y `amd/build/enviarnotas.min.js` — OK (Node v24.13.1).
- [x] Smoke-test de comportamiento con DOM simulado (ambos archivos, src y min):
      listado con 2 estudiantes + 1 sin nota (formato `8.5 / 10`, `Sin nota`),
      escapar de comilla y HTML en nombres, seleccionar todos/deseleccionar,
      contador, preview (args `courseid/groupid/comment` del primer seleccionado),
      envío (userids correctos), badges `sent`/``not_enrolled`, resumen,
      restauración del botón y alerta sin selección — ambos `SMOKE OK`.
- [ ] Runtime (Moodle de prueba): página completa operativa EN/PT.

---

### Fase 4 — QA y tests automatizados  ⬜

**Objetivo:** regresión protegida por PHPUnit + checklist manual.

Cambio | Detalle
| --- | --- |
| `tests/send_grades_mail_test.php` | NUEVO — tests: capability; lote mixto (2 matriculados + 1 no matriculado); preview no fuga notas de otros; curso sin items de calificación; string de sujeto y HTML contienen la nota global del estudiante |

Checklist manual |
| --- |
| [ ] Instalar el plugin actualizado en una Moodle 4.x de prueba. |
| [ ] Curso 2: asignación con nota 8.5/10 visible + 1 ítem `hidden` → el correo incluye la visible y omite la oculta. |
| [ ] Enviar a 3 estudiantes → 3 correos (bandeja de prueba), 3 mensajes en Moodle, 3 filas de auditoría. |
| [ ] Reenviar a 1 estudiante → funciona y queda segunda auditoría. |
| [ ] Docente sin `manageinterventions` → botón oculto y WS rechaza. |
| [ ] Estudiante sin ninguna nota → "Sin nota registrada" y envío exitoso. |

---

### Fase 5 — Documentación  ⬜

**Objetivo:** el plugin queda documentado para el próximo desarrollador.

- [ ] `README.md`: sección "Grades notification" (cómo enviar notas, qué incluye el correo,
      seguridad y auditoría, idioma).
- [ ] `ESTRUCTURA.md`: actualizar mapa de archivos, flujo, tabla de web services
      (+2 nuevos), y tabla "Quiero… → archivo".
- [ ] Changelog en `README.md`: "Version 4.3.1 — Grades email module".

---

## 5. Riesgos y mitigaciones

| Riesgo | Mitigación |
| --- | --- |
| Fuga de notas de otros estudiantes en el correo | 1 WS por estudiante; solo campos de esa fila; test explícito en Fase 4 |
| Correo con ítems `hidden` | `api::get_grades()` ya filtra; verificado en checklist Fase 4 |
| Fallo parcial en el lote (1 de N correos) | El envío es por estudiante; el resultado devuelve éxito/error individual y no aborta el resto |
| `amd/build` sin Grunt disponible | El min de `enviarnotas` es un único módulo simple y se entrega ya minificado junto al `src` |
| Moodle message provider inhabilitado | El envío del mensaje Moodle no falla el envío del correo; el resultado reporta el canal que falló |
| Versiones de la BD | No hay cambios de esquema en esta funcionalidad (se reutiliza la tabla de mensajes existente) → solo subida de `version.php` |

## 6. Orden de avance

> Se avanza fase a fase; cada fase debe pasar sus criterios de aceptación antes de
> comenzar la siguiente.

1. **Fase 1** — Núcleo PHP (WS + auditoría)
2. **Fase 2** — Página + botón del bloque
3. **Fase 3** — JS, CSS, idiomas
4. **Fase 4** — Tests y checklist
5. **Fase 5** — Docs

Estado: `⬜` pendiente · `🔄` en curso · `✅` completada

- [x] Fase 1 — ✅ código completo (criterios de runtime pendientes de verificar en Moodle de prueba)
- [x] Fase 2 — ✅ código completo (criterios de runtime pendientes de verificar en Moodle de prueba)
- [x] Fase 3 — ✅ código completo (criterios de runtime pendientes de verificar en Moodle de prueba)
- [ ] Fase 4
- [ ] Fase 5

## 7. Punto de corte (estado actual)

**Fecha de corte:** 19-oct-2026 · **Estado de la rama de código: FASE 1 COMPLETA (código)**
(`external.php`, `db/services.php`, `version.php`, `lang/en` y `lang/pt_br` modificados;
`php -l` pasa en todos los archivos).

### Lo que QUEDABA de la Fase 1 (en orden) — TODO COMPLETADO ✅

1. ✅ **`classes/external.php`** — Insertado el bloque Grades mail (helper `build_grades_mail`
   + WS `get_grades_mail_preview` + WS `send_grades_mail` con sus `*_parameters()` y
   `*_returns()`). Punto exacto de inserción: **después** del cierre `}` de
   `send_message_returns()` (línea ~783) y **antes** del comentario de sección
   `get_interventions` (~línea 785).
   ⚠️ **Ancla sugerida para el edit (ASCII, sin caracteres ═):**
   ```
            'recordid' => new \external_value(PARAM_INT,  'ID in block_mwa_dashboard_messages'),
        ]);
    }
   ```
   (Es única en el archivo. No incluir en el `oldString` el comentario con `═`/`—`/`õ`.)
   NOTA de cierre: la indentación real del ancla en el archivo es 12/8/4 espacios
   (no 9/5/4 como se anotó al escribir el plan); el edit se aplicó con la real.

2. ✅ **`db/services.php`** — Registrados los 2 WS tras la entrada de `get_grades`:
   ```php
   'block_mwa_dashboard_get_grades_mail_preview' => [
       'classname'     => 'block_mwa_dashboard\external',
       'methodname'    => 'get_grades_mail_preview',
       'description'   => 'Preview the exact grades email that will be sent to a student',
       'type'          => 'read',
       'ajax'          => true,
       'loginrequired' => true,
   ],
   'block_mwa_dashboard_send_grades_mail' => [
       'classname'     => 'block_mwa_dashboard\external',
       'methodname'    => 'send_grades_mail',
       'description'   => 'Send the grades notification by email and Moodle message to one or more students',
       'type'          => 'write',
       'ajax'          => true,
       'loginrequired' => true,
   ],
   ```

3. ✅ **`version.php`** — `$plugin->version = 2026082689;` → `$plugin->version = 2026082690;` (aplicado)

4. ✅ **`lang/en/` y `lang/pt_br/`** — (adelanto de Fase 3) Agregados los strings de
   contenido del correo que usa el backend, para que los emails sean legibles:
   `gradesmail_email_subj`, `gradesmail_email_hi`, `gradesmail_email_intro`,
   `gradesmail_email_global`, `gradesmail_email_no_grade`, `gradesmail_email_activity`,
   `gradesmail_email_grade`, `gradesmail_email_pending`,
   `gradesmail_email_commentlabel`, `gradesmail_email_footer`.
   (Los strings de UI `gradesmail_*` sin el sufijo `email_` siguen en la Fase 3.)
   NOTA: además se añadieron `gradesmail_email_subj_nograde` (asunto cuando no hay
   nota global) y `gradesmail_error_nonestudent` (envío sin estudiantes), ambos
   necesarios para que los mensajes de error/estado sean legibles.

### Criterios pendientes de verificar al cerrar la Fase 1

- [x] `php -l` pasa en `external.php`, `services.php`, `version.php` y en los archivos de `lang` — verificado con PHP 8.3, 19-oct-2026.
- [ ] Lote mixto (2 matriculados + 1 no matriculado) → `sent=2, failed=1`; 2 filas
      nuevas en `block_mwa_dashboard_messages` con `status='sent'`,
      `send_type='both'`, `intervention_reason='Grades'`.
- [ ] `get_grades_mail_preview` devuelve HTML **sin** notas de otros estudiantes.
- [ ] Curso sin ítems de calificación → "sin nota registrada", envío exitoso.

### Reglas de implementación acordadas (no perder)

- 1 correo + 1 mensaje Moodle por estudiante (nunca un correo de grupo).
- Solo ítems `hidden=0`; datos de `api::get_grades()` como fuente única.
- Capabilities: `block/mwa_dashboard:view` + `block/mwa_dashboard:manageinterventions`.
- Sin snapshot de intervención (no es una intervención pedagógica).
- El envío de un estudiante que falle NO interrumpe al resto (resultado individual).

## 8. Punto de corte actual — sesión 19-oct-2026 (resumen operativo)

**Estado del código:** Fase 1 COMPLETA (código). **No se creó ningún archivo de la
Fase 2/3/4** (verificado: `enviarnotas.php`, `amd/src/enviarnotas.js`,
`amd/build/enviarnotas.min.js`, `styles/gradesmail.css`, `tests/send_grades_mail_test.php`
todavía no existen).

### Cambios aplicados en esta sesión

| Archivo | Cambio |
| --- | --- |
| `classes/external.php` | Insertado el bloque `// -- grades mail --` (líneas ~785-1120): `grades_mail_format_value()`, `build_grades_mail()`, `get_grades_mail_preview[_parameters/_returns]`, `send_grades_mail[_parameters/_returns]` — todo **después** de `send_message_returns()` y **antes** de `get_interventions_parameters()` |
| `db/services.php` | Registrados `block_mwa_dashboard_get_grades_mail_preview` (read) y `block_mwa_dashboard_send_grades_mail` (write), tras la entrada `get_grades` |
| `version.php` | `2026082689` → `2026082690` |
| `lang/en` y `lang/pt_br` | 12 strings de contenido de correo: `gradesmail_email_subj`, `gradesmail_email_subj_nograde`, `gradesmail_email_hi`, `gradesmail_email_intro`, `gradesmail_email_global`, `gradesmail_email_no_grade`, `gradesmail_email_activity`, `gradesmail_email_grade`, `gradesmail_email_pending`, `gradesmail_email_commentlabel`, `gradesmail_email_footer`, `gradesmail_error_nonestudent` |

### Convenios del proyecto descubiertos en esta sesión (usar tal cual en Fase 2/3)

- **Cargar el JS AMD** en una página: `$PAGE->requires->js_call_amd('block_mwa_dashboard/<módulo>', 'init', [parámetros]);`
  (patrón verificado en `settings.php` línea 52, módulo `adminai`).
- **Cargar CSS del plugin**: `$PAGE->requires->css(new moodle_url('/blocks/mwa_dashboard/styles/<archivo>.css'));`
  (patrón verificado en `classes/output/dashboard_page.php` línea 56).
- **`amd/build/*.min.js`**: TODOS los módulos de `amd/src` tienen su `.min.js` en
  `amd/build` (verificado: 14 archivos). No hay Grunt disponible → el
  `enviarnotas.min.js` debe entregarse minificado a mano/compactado (mismo criterio del plan).
- **Página `enviarnotas.php`**: sigue la estructura de `view.php` (licencia,
  `require_once('../../config.php')`, `required_param('course', PARAM_INT)`,
  `require_login`, `require_capability` ×2 (`view` + `manageinterventions`),
  `$PAGE->set_url('/blocks/mwa_dashboard/enviarnotas.php', ...)`, layout `embedded`,
  `$PAGE->set_cacheable(false)`). Contenedor: `<div id="mwaGradesMailRoot" data-courseid="..." data-groupid="...">`.
- **Botón del bloque**: en `block_mwa_dashboard.php::get_content()`, insertar el link
  "Enviar notas" dentro de la concatenación de `$this->content->text` (líneas 245-256),
  entre el link "Abrir dashboard" (250-253) y `$capturecontrol` (254), visible solo con
  `manageinterventions` y curso id > 1.
- **Respuesta del WS `get_grades` (EXISTENTE)**: `{grades: <string JSON>, count}` — en JS
  hay que `JSON.parse(response.grades)`. Fila de estudiante: `__mwa_type__==='student'`,
  `First name`, `Last name`, `Course total (Grade)` (null si no hay nota).
- **Respuesta de `send_grades_mail`** (nuevo): `{success, sent, failed, results[]}` con
  `results[] = {userid, success, status('sent'|'error'|'not_enrolled'), recordid, emailsent, moodlesent}`.
- **Respuesta de `get_grades_mail_preview`** (nuevo): `{subject, html, plain, studentname}`.

### Strings de UI pendientes para la Fase 2/3 (lista ya definida en esta sesión)

agregar en `lang/en` y `lang/pt_br` al final del archivo (después del bloque
`gradesmail_error_nonestudent`): `gradesmail_button`, `gradesmail_title`,
`gradesmail_page_desc`, `gradesmail_jsnotice`, `gradesmail_students`,
`gradesmail_col_student`, `gradesmail_col_grade`, `gradesmail_selectall`,
`gradesmail_deselectall`, `gradesmail_selectedcount` ({count}),
`gradesmail_comment`, `gradesmail_commentplaceholder`, `gradesmail_preview`,
`gradesmail_previewtitle`, `gradesmail_previewsubject`, `gradesmail_send`,
`gradesmail_sending`, `gradesmail_confirm_send`, `gradesmail_noselection`,
`gradesmail_nostudents`, `gradesmail_results`, `gradesmail_status_sent`,
`gradesmail_status_error`, `gradesmail_status_notenrolled`,
`gradesmail_channel_email`, `gradesmail_channel_moodle`,
`gradesmail_nograde`, `gradesmail_error_generic`.
(en: 'Send grades', 'Send grades to students', 'Select one or more students, preview the exact message and send their grades (email + Moodle message).', 'This page requires JavaScript to be enabled. Please enable it and reload the page.', 'Students', 'Student', 'Overall result', 'Select all', 'Deselect all', '{count} student(s) selected', 'Message to include in the email (optional)', 'A note for the students, included below the grades table in the email...', 'Preview', 'Email preview', 'Subject', 'Send grades', 'Sending...', 'Send the grades to all the selected students? Each student will receive one email and one Moodle message.', 'Select at least one student first.', 'No enrolled students found in this course.', 'Results', 'Sent', 'Error', 'Not enrolled', 'Email', 'Moodle message', 'No grade yet', 'The request failed. Please try again.'
pt_br: 'Enviar notas', 'Enviar notas para os estudantes', 'Selecione um ou mais estudantes, visualize a mensagem exata e envie as notas deles (e-mail + mensagem do Moodle).', 'Esta página exige JavaScript. Ative o JavaScript e recarregue a página.', 'Estudantes', 'Estudante', 'Resultado global', 'Selecionar todos', 'Desmarcar todos', '{count} estudante(s) selecionado(s)', 'Mensagem para incluir no e-mail (opcional)', 'Uma nota para os estudantes, incluída abaixo da tabela de notas no e-mail...', 'Pré-visualizar', 'Pré-visualização do e-mail', 'Assunto', 'Enviar notas', 'Enviando...', 'Enviar as notas para todos os estudantes selecionados? Cada um deles receberá um e-mail e uma mensagem do Moodle.', 'Selecione pelo menos um estudante primeiro.', 'Nenhum estudante matriculado encontrado neste curso.', 'Resultados', 'Enviado', 'Erro', 'Não matriculado', 'E-mail', 'Mensagem do Moodle', 'Sem nota ainda', 'A requisição falhou. Tente novamente.'

### ~~Primeros pasos de la próxima sesión (en orden)~~ — SUPERADO por la sesión de abajo

> 1. ~~Strings de UI en `lang/en` y `lang/pt_br`.~~ ✅
> 2. ~~`enviarnotas.php` (esqueleto Fase 2).~~ ✅
> 3. ~~Botón "Enviar notas" en `block_mwa_dashboard.php`.~~ ✅
> 4. Fase 3: `styles/gradesmail.css` + `amd/src/enviarnotas.js` + `amd/build/enviarnotas.min.js`.
> 5. Fase 4: `tests/send_grades_mail_test.php` + checklist manual en Moodle de prueba.
> 6. Fase 5: `README.md` + `ESTRUCTURA.md`.

## 9. Punto de corte actual — sesión 19-oct-2026 (Fase 2 cerrada en código)

**Estado del código:** Fases 1 y 2 COMPLETAS (código). Fase 3 sin empezar
(`amd/src/enviarnotas.js`, `amd/build/enviarnotas.min.js`, `styles/gradesmail.css`,
`tests/send_grades_mail_test.php` todavía no existen).

### Cambios aplicados en esta sesión

| Archivo | Cambio |
| --- | --- |
| `lang/en/block_mwa_dashboard.php` | +28 strings de UI `gradesmail_*` tras `gradesmail_error_nonestudent` (lista completa en §8; valores textuales del plan) |
| `lang/pt_br/block_mwa_dashboard.php` | +28 strings de UI `gradesmail_*` (traducciones del plan) |
| `enviarnotas.php` | **NUEVO** — página protegida (`require_login` + caps `view` + `manageinterventions`), layout `embedded`, título `gradesmail_title`, contenedor `<div id="mwaGradesMailRoot" data-courseid data-groupid="0">` con h1 + descripción + alerta `gradesmail_jsnotice` (esqueleto; el JS reemplaza este contenido en Fase 3) |
| `block_mwa_dashboard.php` | Botón "Enviar notas" (líneas 245-255, 266) entre "Abrir dashboard" y el switch de captura; solo con `manageinterventions` y `courseid > 1`; link a `/blocks/mwa_dashboard/enviarnotas.php?course=X` |
| `version.php` | **Sin cambios** (2026082690 sigue siendo la correcta; no hay cambios de esquema en Fase 2) |

### Verificación

- [x] `php -l` pasa en `enviarnotas.php`, `block_mwa_dashboard.php`, `lang/en/…`, `lang/pt_br/…` (PHP 8.3, 19-oct-2026).
- [ ] Runtime (Moodle de prueba): ver bloque con 2 botones; página carga sin errores; sin `manageinterventions` → sin botón + página "no tiene permiso".

### Primeros pasos de la próxima sesión (en orden) — FASE 3

1. `styles/gradesmail.css` (NUEVO) — tarjeta/contenedor, tabla de estudiantes, badges de estado, área de preview; registrarla en `enviarnotas.php` con `$PAGE->requires->css(new moodle_url('/blocks/mwa_dashboard/styles/gradesmail.css'))`.
2. `amd/src/enviarnotas.js` (NUEVO) — `init({courseid, groupid, str})`:
   - WS `block_mwa_dashboard_get_grades` → `JSON.parse(response.grades)`; filas `__mwa_type__==='student'` → lista con checkbox + `Course total (Grade)` (null → `gradesmail_nograde`).
   - Seleccionar todos / deseleccionar todos; contador `gradesmail_selectedcount` ({count}).
   - Textarea comentario opcional → param `comment`.
   - `Vista previa` del PRIMER estudiante seleccionado → WS `block_mwa_dashboard_get_grades_mail_preview` (`{subject, html, plain, studentname}`) → renderizar en el área de preview.
   - `Enviar` con `confirm(gradesmail_confirm_send)` → WS `block_mwa_dashboard_send_grades_mail` (`courseid, userids[], comment, groupid`) → `{success, sent, failed, results[]: {userid, success, status, recordid, emailsent, moodlesent}}` → tabla de resultados por estudiante con badges `gradesmail_status_*` y canales `gradesmail_channel_*`.
   - Registro en la página: `$PAGE->requires->js_call_amd('block_mwa_dashboard/enviarnotas', 'init', [{courseid, groupid, str: {…}}])` (patrón `view.php` línea 45) **reemplazando** el esqueleto de la Fase 2 dentro de `#mwaGradesMailRoot`.
3. `amd/build/enviarnotas.min.js` (NUEVO) — minificado a mano (sin Grunt), mismo criterio usado por los otros 14 módulos.
4. Alinear el contenido del preview con el envío (mismo helper server `build_grades_mail()`): la vista previa y el correo final son el mismo texto.
5. Cerrar Fase 3 contra los criterios de aceptación de §4 y luego Fase 4 (tests + checklist).

## 10. Punto de corte actual — sesión 19-oct-2026 (Fases 1-3 cerradas en código)

**Estado del código:** Fases 1, 2 y 3 COMPLETAS (código). Fase 4 sin empezar
(`tests/send_grades_mail_test.php` todavía no existe). Los criterios de runtime
de las 3 fases siguen pendientes de verificar en una Moodle de prueba.

### Cambios aplicados en esta sesión (Fase 3)

| Archivo | Cambio |
| --- | --- |
| `styles/gradesmail.css` | **NUEVO** — estilos `mwa-gm-*` (tarjetas, tablas, botones, badges de estado, área de preview/mail, canales, aviso de error) |
| `amd/src/enviarnotas.js` | **NUEVO** — `init({courseid, groupid, str})` completo: lista de estudiantes con checkbox y nota global, seleccionar/deseleccionar todos, contador, comentario opcional, Vista previa (primer seleccionado, vía WS), Envío (confirmación + estado "enviando" + tabla de resultados por estudiante con canales) |
| `amd/build/enviarnotas.min.js` | **NUEVO** — 1 renglón, 9,384 bytes, minificación string/comment/regex-aware |
| `enviarnotas.php` | **MOD** — registro de `styles/gradesmail.css` + `js_call_amd('block_mwa_dashboard/enviarnotas','init',[{courseid, groupid:0, str:{…24 strings}}])` |
| `PLAN-enviarnotas.md` | **MOD** — Fase 3 → ✅, detalles de implementación, verificación y este punto de corte |

### Verificación Fase 3

- [x] `php -l` en `enviarnotas.php` (PHP 8.3).
- [x] `node --check` en `amd/src/enviarnotas.js` y `amd/build/enviarnotas.min.js` (Node v24.13.1).
- [x] Smoke-test DOM-simulado idéntico en ambos archivos: listado, nota `8.5 / 10`,
      `Sin nota`, escape de comilla/HTML, selection all/none, contador,
      preview (curso/grupo/comentario del primer seleccionado), envío (userids),
      badges `sent`/`not_enrolled`, resumen, restauración del botón, alerta sin selección.
- [ ] Runtime Moodle de prueba: página completa EN y PT (criterios §4 Fase 3).

### Primeros pasos de la próxima sesión — FASE 4

1. `tests/send_grades_mail_test.php` (nuevo) — ver §4 Fase 4: capability;
   lote mixto 2+1; preview sin fuga entre estudiantes; curso sin items;
   subject/HTML contienen la nota del estudiante.
2. Checklist manual en Moodle de prueba (criterios runtime de las 3 fases).
3. Fase 5: `README.md` + `ESTRUCTURA.md` + changelog `4.3.1`.
