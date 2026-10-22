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

## 11. Punto de corte actual — sesión 22-oct-2026 (nuevo alcance pedido; servidor mapeado; frontend por implementar)

> **Modo de trabajo acordado con el usuario:** **paso a paso, probando cada paso y con su
> confirmación antes de avanzar** ("implementemos paso a paso y vamos probando, yo te confirmo").
> Esta sección documenta (a) el alcance nuevo, (b) el estado exacto del código local y del
> servidor, (c) cómo desplegar, (d) las decisiones de diseño abiertas y (e) el plan de ejecución.

### 11.1 Alcance nuevo pedido por el usuario (prioritario, supera lo de §4)

1. **Selector de Actividad** (drop-down): elegir qué actividad específica se está evaluando si
   el curso tiene varias. **Backend ya listo** (§11.2).
2. **Nota mínima de aprobación (pass mark)**: campo editable precargado con el `gradepass` de
   Moodle de la actividad (si no hay → fallback 50% del máximo; todo editable en el campo).
3. **Filtros rápidos en la tabla de estudiantes**: 🟢 Aprobados · 🔴 Reprobados ·
   🟡 Sin entrega/pendientes (+ "Todos").
4. **Historial de envíos (logs y auditoría)**: pestaña con fecha, hora, docente que envió,
   estudiantes receptores y si se entregó o rebotó. **Backend ya listo** (§11.2).
5. **Analizar si hacen falta más filtros** → decisión tomada: además de los botones 🟢🔴🟡 se
   agrega **búsqueda por nombre** (la página no carga grupos, así que grupo queda fuera).
6. **Confirmaciones bonitas y centradas**: reemplazar `window.confirm` / `window.alert` del JS
   actual por un modal centrado con backdrop. Las strings `confirm_*` / `error_title` ya existen.

**Decisión de diseño clave:** la nota de aprobación **NO cambia el contenido del correo/mensaje**
(el correo siempre lleva nota global + desglose de TODAS las actividades, y así lo dice
`gradesmail_confirm_passnote`). Se usa **solo** para la clasificación cliente (columna "Resultado"
+ filtros 🟢🔴🟡) y para el texto de confirmación. → **No hay que tocar los WS de
`send_grades_mail` ni de `get_grades_mail_preview`**.

### 11.2 Estado local del código (Git, verificado y commiteado en esta sesión)

Repo dentro del folder del plugin: `...\moodle-block_mwa_dashboard\block_mwa_dashboard`
rama `main` · commit inicial `408cb0b` "Initial commit: block_mwa_dashboard v4.3 (2026101901)".

**✅ LISTO Y COMMITADO (backend nuevo; estaba sin commitear al iniciar la sesión):**

| Archivo | Cambio |
| --- | --- |
| `classes/external.php` | + WS `get_grades_send_options()` (≈L1165-1293): `activities:[{seq, cmid, name, modname, grademax, passmark, hascustompassmark}]`; `seq` 1..N alineado con las columnas `act_N` de `api::get_grades()`; passmark = `gradepass` explícito si existe (`hascustompassmark=true`) o 50% de `grademax` si no. + WS `get_grades_mail_history()` (≈L1295-1432): auditoría `block_mwa_dashboard_messages` con `intervention_reason='Grades'`, con `limit`(1-100,def 25)/`offset` y filtro `status` `''|sent|error`, respeta `groupid`, orden `timesent DESC`, formatea fecha/hora con `userdate` (2 campos separados) y nombres según `fullnameformat`. |
| `db/services.php` | Registrados `block_mwa_dashboard_get_grades_send_options` (read) y `block_mwa_dashboard_get_grades_mail_history` (read), ambos `ajax` + `loginrequired` |
| `lang/en/...php` (152,931 B) | +37 strings `gradesmail_*` (lista §11.4) |
| `version.php` | `2026101901` → **`2026102201`** (la subida a servidor dispara la upgrade del plugin y registra los 2 WS) |

**❌ PENDIENTE (nada de esto se tocó aún):**

| Archivo | Qué falta |
| --- | --- |
| `amd/src/enviarnotas.js` (15,073 B, **versión VIEJA**) | Re-escritura: pestañas [Enviar · Historial], select de actividad, campo passmark con hints, filtros 🟢🔴🟡+Todos, búsqueda por nombre, columna "Resultado de la actividad" con badge, modal centrado, alertas, pestaña historial con "Cargar más" y filtro de estado |
| `amd/build/enviarnotas.min.js` (8,081 B) | Regenerar minificada tras re-escritura (minificado a mano, sin Grunt, igual que las otras 14) |
| `enviarnotas.php` (4,132 B) | `$gmkeys[]`: añadir las 37 claves nuevas (hoy solo las 24 de la v1, L43-49) |
| `lang/es/...php` (4,120 B) | +37 strings (hoy solo 40 `gradesmail_*` viejos) → **si no, el JS mostrará claves en crudo** |
| `lang/pt_br/...php` (157,638 B) | +37 strings (consistencia EN/PT/ES) |
| `styles/gradesmail.css` (5,071 B) | Estilos de tabs, chips de filtros con emoji, select/input, badges de resultado, tabla del historial, modal centrado con backdrop, alertas |

### 11.3 Estado del servidor (verificado por SSH en esta sesión)

**Acceso**
- Sitio `https://medicionra.uniclaretiana.edu.co/` · Moodle `admin` / `Unicla.m&m.2026`
- Servidor `192.168.25.4` · `proyectosmym` / `Unicla.002` · SSH OK · **tiene acceso a `docker`**,
  pero **NO puede leer** el volumen del host (`/var/lib/docker/volumes/...` → `Permission denied`).
- **Moodle corre en Docker (Bitnami)**: contenedor `moodle_app` (imagen `moodle-moodle`,
  puertos 8080/8443), docroot **`/bitnami/moodle`** (volumen `moodle_moodle_data`);
  moodledata = volumen `moodle_moodledata_data`.
- **Moodle core:** `$version = 2025041401.00` → rama Moodle 5.x (usuario indica 5.0.1).
- **Plugin en servidor:** `blocks/mwa_dashboard` con `version.php = 2026101901`
  (= commit inicial local, **aún sin el backend nuevo**; verificar §11.6.1).
- **DB:** `dbhost=192.168.25.7 dbname=moodle_db dbuser=moodle_user`, clave de 15 caracteres en
  `/bitnami/moodle/config.php` (**NO copiarla a documentos**). Contenedor MySQL `capsu_mysql`
  (mysql:8.0, 3306 publicado). `moodle_user` es ACL por host: desde `capsu_mysql` a `127.0.0.1`
  → `Access denied`; **hay que consultar desde dentro de `moodle_app`** (PHP) o con el cliente
  mysql del host si existe (no verificado).
- Nginx: `nginx-proxy-manager` (`npm`) + `cloudflare_tunnel` en 80/443. **No hay PHP CLI en el
  host** (sí dentro de `moodle_app`). Node local disponible (v24).

**Contraste de tamaños local ↔ servidor (todo coincide, es la copia V1 ya desplegada):**

| Archivo | Local | Servidor |
| --- | --- | --- |
| `enviarnotas.php` | 4,132 B | 4,132 B (owner 1000:1000) |
| `amd/src/enviarnotas.js` | 15,073 B | 15,073 B |
| `amd/build/enviarnotas.min.js` | 8,081 B | 8,081 B + residuo `enviarnotas.min.js.bak-20261020` (9,384 B) |

⚠️ **Posible regresión ACTIVA en la página desplegada:** hoy `gradesmail_confirm_send` en
`lang/en` vale `'Send now'` (nuevo label del modal), pero el JS VIEJO aún en producción usa esa
misma clave como texto del `window.confirm` (`enviarnotas.js` L325 + `$gmkeys` de
`enviarnotas.php`) → la confirmación del botón Enviar diría "Send now" en inglés.
Verificar con:
`docker exec moodle_app grep -n "gradesmail_confirm_send" /bitnami/moodle/blocks/mwa_dashboard/lang/*/block_mwa_dashboard.php`
y si el servidor tiene la misma línea, **restablecer el texto largo original** (o que el JS nuevo
use `confirm_send` SOLO como botón del modal y otra clave para el mensaje).

**Procedimiento de deploy (verificado en inventario, no ejecutado en esta sesión):**
1. `Set-SCPItem -ComputerName 192.168.25.4 -Credential $cred -Path <archivo> -Destination /tmp`
   (Posh-SSH instalado; **no existe** `Set-SCPFile` en esta versión, y **no acepta** `-NoEOL`).
2. `docker cp /tmp/<archivo> moodle_app:/bitnami/moodle/blocks/mwa_dashboard/<ruta>`
   (usar `/` para carpetas `amd/src/`, `amd/build/`, `lang/es/`, etc.).
3. Si se subió `version.php` con `2026102201`, la siguiente petición web de Moodle corre la
   upgrade del plugin → **verificar** que los 2 WS aparecieron en `mdl_externalfunctions` (§11.6.2).
4. Limpiar caché del navegador (el JS carga con `?ver=` de la versión del plugin) antes de probar.

### 11.4 Las 37 cadenas nuevas (hechas en `lang/en`; faltan `es` y `pt_br`)

`gradesmail_tab_send`, `tab_history`, `evalselect`, `passmin`, `passmin_custom`,
`passmin_fallback`, `passmin_none`, `filter_all`, `filter_passed`, `filter_failed`,
`filter_pending`, `searchplaceholder`, `resultcol`, `result_passed`, `result_failed`,
`result_pending`, `hist_title`, `hist_empty`, `hist_more`, `hist_state_all`,
`hist_state_sent`, `hist_state_error`, `col_date`, `col_time`, `col_teacher`, `col_status`,
`col_subject`, `hist_bounced`, `confirm_title`, `confirm_body` (placeholders `{count}`,
`{activity}`, `{passnote}`), `confirm_passnote`, `confirm_noactivity`, `confirm_cancel`,
`confirm_send` (= `Send now`), `alert_noselection`, `error_title`, `error_generic`.

### 11.5 Contrato de los WS nuevos (para escribir el JS sin adivinar)

- `block_mwa_dashboard_get_grades_send_options {courseid, groupid=0}` →
  `{activities: [{seq, cmid, name, modname, grademax, passmark, hascustompassmark}]}`
  (`grademax=0` / `passmark=0` cuando la actividad no tiene grado).
- `block_mwa_dashboard_get_grades_mail_history {courseid, status='', groupid=0, limit=25, offset=0}` →
  `{total, entries: [{recordid, timesent, date, time, teacherid, teachername, studentid,
  studentname, status('sent'|'error'), send_type('both'), subject(≤90 c), moodlelinked}]}`
- Recordatorio (WS viejo en uso): `get_grades` → filas `{__mwa_type__:'student', 'User ID',
  'First name', 'Last name', 'Course total (Grade)', 'Course total max (Grade)', act_1..act_N}`
  + fila meta `{__mwa_type__:'activity_names', act_i, act_cmid_i, act_module_i}`.

### 11.6 Verificaciones para ARRANCAR la próxima sesión (sin tocar nada todavía)

1. **¿El servidor tiene ya el backend nuevo?** (¿o solo está en local?)
   ```bash
   docker exec moodle_app sh -c 'grep -c "function get_grades_send_options\|function get_grades_mail_history" /bitnami/moodle/blocks/mwa_dashboard/classes/external.php; grep -c "get_grades_send_options" /bitnami/moodle/blocks/mwa_dashboard/db/services.php'
   ```
   (esperado `0` → hay que desplegar `external.php` + `services.php` + `version.php` **antes** de
   probar el dropdown/historial).
2. **¿Están registrados los 2 WS en la DB?** Receta (dentro de `moodle_app`, sin exponer clave):
   1. Crear localmente `db_probe.php` que lea `dbhost/dbname/dbuser/dbpass` de
      `/bitnami/moodle/config.php` con regex, abra PDO y corra las consultas.
   2. `Set-SCPItem` → `/tmp/db_probe.php` en el host →
      `docker cp /tmp/db_probe.php moodle_app:/tmp/db_probe.php && docker exec moodle_app php /tmp/db_probe.php`.
   Consultas:
   ```sql
   SELECT component, plugin, version FROM mdl_plugin WHERE component='block_mwa_dashboard';
   SELECT name FROM mdl_externalfunctions WHERE name LIKE 'block_mwa_dashboard_%' ORDER BY name;
   SELECT id, shortname, fullname FROM mdl_course WHERE id>1 AND (fullname LIKE '%Prueba funcional%' OR fullname LIKE '%Momento%' OR shortname LIKE '%Prueba%' OR shortname LIKE '%Momento%') ORDER BY id LIMIT 20;
   ```
   (la última identifica el curso **"Prueba funcional – Momento 1 RA"** que se verá en la prueba).
3. **Regresión `confirm_send`** en `lang/en` del servidor (§11.3 ⚠️).
4. **Preguntar al usuario** las 3 decisiones de §11.7 y que confirme el plan de §11.8.

### 11.7 Decisiones de diseño ABIERTAS (preguntar al usuario; primera opción = mi recomendación)

1. **¿Si NO se elige ninguna actividad, con qué nota se clasifica 🟢🔴🟡?** (el passmark existe
   solo por actividad; el total del curso no tiene passmark en el backend actual)
   - (a) *Recomendado:* el select lista **solo actividades**; si un curso no tiene actividades
     selectables, se deshabilitan el select y los filtros con un aviso; el correo siempre
     envía global + desglose de todas las actividades.
   - (b) Agregar opción "Resultados globales" que clasifica contra el passmark del total del
     curso → **requiere tocar backend** (`get_grades_send_options`).
   - (c) Opción "Resultados globales" pero con los filtros deshabilitados (solo enviar correo).
2. **Semántica exacta de los filtros 🟢🔴🟡**
   - (a) *Recomendado:* **filtran las filas visibles Y marcan el checkbox** de ese grupo
     (deseleccionando los del resto); "Todos" vuelve a ver todos sin tocar la selección.
   - (b) Solo marcan (no ocultan filas).
   - (c) Solo filtran (no tocan checkboxes).
3. **Flujo de prueba**
   - (a) *Recomendado:* por cada paso → desplegar al servidor → probar en el navegador con
     `admin/Unicla.m&m.2026` → el usuario hace su check y confirma → siguiente paso.
   - (b) Construir todo primero y probar en bloque al final.

### 11.8 Plan de ejecución de la próxima sesión (paso a paso, con confirmación entre pasos)

- **Paso 0 — Verificar servidor** (recetas §11.6) + preguntar §11.7. **No tocar nada** hasta
  tener respuestas.
- **Paso 1 — Cadenas:** `lang/es` + `lang/pt_br` (+37 strings c/u).
- **Paso 2 — `enviarnotas.php`:** `$gmkeys[]` ← 37 claves nuevas (sin esto el JS las ve en crudo).
- **Paso 3 — Deploy de backend** (`external.php` + `services.php` + `version.php`) si §11.6.1-2
  lo requieren → comprobar `mdl_externalfunctions` y probar los 2 WS. *(pausa: usuario confirma)*
- **Paso 4 — Frontend "Enviar notas":** tabs, select de actividad, campo passmark, filtros
  🟢🔴🟡+Todos, búsqueda por nombre, columna "Resultado" con badge + `styles/gradesmail.css`
  → `.min.js` → deploy → probar en el curso **"Prueba funcional – Momento 1 RA"**. *(usuario confirma)*
- **Paso 5 — Pestaña "Historial":** `get_grades_mail_history`, tabla, filtro de estado,
  "Cargar más" → deploy → probar. *(usuario confirma)*
- **Paso 6 — Modales:** confirmación centrada + alerta de error (sustituyen
  `window.confirm`/`window.alert`) → deploy → probar. *(usuario confirma)*
- **Paso 7 — Cierre:** `php -l` en los PHP tocados, `node --check` en ambos JS, commit Git con
  resumen, update de este plan. (Si queda tiempo: antigua Fase 4 tests / Fase 5 docs.)

### 11.9 Notas técnicas que NO perder (descubiertas en esta sesión)

- `enviarnotas.php` define `$gmkeys[]` (L43-49, **24 claves hoy**) y el JS las consume como
  `str[key]`; el prefijo `gradesmail_` lo agrega PHP (`get_string('gradesmail_'.$gmkey, ...)`).
  **Las claves nuevas deben meterse ahí o el JS las verá en crudo.**
- El JS viejo usa `define(['core/ajax'], ...)` → **este core NO tiene `core/webservices`**;
  seguir con `Ajax.call([{methodname, args}])[0]`.
- `amd/build/*.min.js` se minifican a mano (sin Grunt en este repo); hay un `.bak` residual
  **solo en el servidor** (no importarlo).
- `api::get_grades()` solo lista estudiantes con rol `student` asignado en el contexto del curso
  (si el curso no tiene ese rol → `[]`, la lista queda vacía).
- El correo/mensaje se construyen en el **idioma del estudiante** (swap de `$USER` en
  `send_grades_mail` y en `get_grades_mail_preview`) — no romperlo si se toca ese código.
- Capabilities de todos los WS: `block/mwa_dashboard:view` +
  `block/mwa_dashboard:manageinterventions` (ya definidas en `db/access.php`, archetypes
  teacher/editingteacher/manager).
- **Código sin commitear al final de cada paso:** hacer commit incremental (ya se hizo uno de
  esta sesión con el backend nuevo; repetir el patrón en cada paso).

## 12. Punto de corte — sesión 22-oct-2026 (Paso 3 — Deploy de backend)

**Estado: backend DESPLEGADO, VERIFICADO EN BD Y CON SMOKE TEST CLI PASS (ver §13).**
Se corrigió en el camino un bug real de SQL en `get_grades_mail_history` (LIMIT/OFFSET
con parámetros nombrados → error de sintaxis en MariaDB/mysqli).

| # | Tarea | Estado |
| --- | --- | --- |
| 0 | Verificar servidor (§11.6) + decisiones §11.7 | ✅ |
| 1 | Strings ES/PT (+37 c/u) y EN listo | ✅ (local) |
| 2 | `enviarnotas.php` `$gmkeys[]` 24 → 59 claves | ✅ (local) |
| 2b | Bug latente `lang/en` L2200 (`gradesmail_confirm_passnote` sin `;`) | ✅ corregido |
| — | `php -l` OK (external.php, services.php, version.php, 3×lang, external.php de clases) | ✅ |
| 3 | **Deploy** `classes/external.php` + `db/services.php` + `version.php` (2026102201) a `moodle_app` | ✅ |
| 3 | Upgrade del plugin ejecutada (`2026102201: éxito`) | ✅ |
| 3 | **Verificación BD**: versión + los 2 WS en `mdl_external_functions` | ✅ PASS/PASS |
| 3 | **Smoke test** de los 2 WS con curso de prueba (id 2) | ✅ **PASS/PASS vía ruta (a) CLI — ver §13** (hallazgos nuevos: no existe la función global `call_external_function` en Moodle 5 + **bug real corregido** en `get_grades_mail_history`) |

### 12.1 Qué se subió y cómo quedó en el servidor

- **Solo 3 archivos** (los de backend), vía `Set-SCPItem` → `/tmp` (host) → `docker cp` →
  `moodle_app:/bitnami/moodle/blocks/mwa_dashboard/` (rutas finales
  `classes/external.php` 154,973 B · `db/services.php` 7,191 B · `version.php` 1,153 B).
- Ownership final: **`1000:1000`** (intento de `chown 1001:0` no quedó aplicado; da igual:
  modos 644/664 → httpd (uid **1001**) los lee igual). Archivo original del bloque:
  `block_mwa_dashboard.php` = `1001:0`.
- Verificación por grep dentro del contenedor: `function get_grades_send_options` = **3**,
  `function get_grades_mail_history` = **3** (método + `_parameters` + `_returns`),
  `block_mwa_dashboard_get_grades_send_options` en `services.php` = 1, …`_mail_history` = 1,
  `version.php` = `2026102201`.
- **NO se subió**: `lang/es`, `lang/pt_br`, `enviarnotas.php`, `amd/*`, `styles/*` (siguen V1
  en producción). Riesgo conocido `confirm_send` (§11.3 ⚠️) → resolverlo en el Paso 4 junto
  con el JS nuevo.

### 12.2 Upgrade del plugin (Moodle 5.0.1 CLI)

Comando que funciona (desde el host, vía SSH):

```bash
docker exec moodle_app sh -c "cd /bitnami/moodle && php admin/cli/upgrade.php --non-interactive"
```

- ❌ `-n` / `-v` ya **no existen** en Moodle 5 → `Opciones no reconocidas` (usar
  `--non-interactive`; `-nv` juntado tampoco).
- ❌ `-u moodle` **no existe** usuario `moodle` en el contenedor (`unable to find user moodle`).
  El usuario web es **uid 1001** (httpd de Bitnami). La CLI corre bien como root (default).
- Salida clave (español): `-->block_mwa_dashboard` · `++ 2026102201: éxito (0,01 segundos) ++` ·
  `Actualización a través de línea de comandos desde 5.0.1 (Build: 20250609) (2025041401) …
  finalizada con éxito`.
- PHP CLI del contenedor: `/opt/bitnami/php/bin/php` **8.2.29**, módulos `mysqli mysqlnd PDO
  pdo_mysql pdo_sqlite`. php.ini: `/opt/bitnami/php/lib/php.ini`, `conf.d` vacío.
  ⚠️ No hay php CLI en el host (confirmado de nuevo).

### 12.3 Verificación en BD (resultado definitivo — script en Moodle CLI)

Resultado real (script `p3_final*.php` ejecutado dentro de `moodle_app`):

```
== config_plugins: plugin=block_mwa_dashboard ==
 - name=version value=2026102201
version_registrada='2026102201'
== external_functions block_mwa_dashboard_* ==
 … 18 funciones del bloque (incluye get_grades_mail_preview y send_grades_mail) …
 -- requisitos del Paso 3 --
PASS  block_mwa_dashboard_get_grades_send_options
PASS  block_mwa_dashboard_get_grades_mail_history
== cursos candidatos ('Prueba') ==
id=2 short=PRUEBA-RA full=Aula de Prueba - Medición de Resultados de Aprendizaje
```

**Receta definitiva de BD (usar en adelante, reemplaza §11.6.2):**

- ❌ **No usar PDO puro**: el DSN `mariadb:` falla con `could not find driver`
  (PHP 8.2/pdo_mysql solo acepta prefijo `mysql:`), y aun mapeando a `mysql:` el login crudo
  con las credenciales extraídas de `config.php` dio
  `Access denied for user 'moodle_user'@'192.168.25.4'`.
- ❌ **No usar `capsu_mysql`** para diagnosticar Moodle: ese MySQL **no es la BD de Moodle**
  (usuarios: `capsu_user@%`, `root` — **no existe `moodle_user`**).
- ✅ **Usar el bootstrap de Moodle**: CLI script `define('CLI_SCRIPT', true);
  chdir('/bitnami/moodle'); require('/bitnami/moodle/config.php');` → `$DB->get_records...`.
  Subir el script con el mismo patrón SCP + `docker cp` + `docker exec moodle_app php /tmp/...`.

**Esquema de Moodle 5.0.1 (diferente a lo asumido en §11.6.2):**

- ❌ **NO existe la tabla `mdl_plugin`** → la versión del plugin está en
  **`config_plugins`** (columnas `id, plugin, name, value`):
  `WHERE plugin='block_mwa_dashboard' AND name='version'`.
- ✅ La tabla **SÍ se llama `mdl_external_functions`** (el usuario tenía razón; en §11.6.2
  estaba escrito mal `mdl_externalfunctions`). **Sin columna `type`**; columnas útiles
  `name, disabled, crontabid, readonly`.
- `get_records()` / `get_records_sql()` devuelven **objetos `stdClass`** → `$r->name`, no
  `$r['name']` (si no: `Cannot use object of type stdClass as array`).

### 12.4 ⚠️ Pendiente del Paso 3: smoke test HTTP de los 2 WS

Intentos fallidos (documentados para no repetirlos):

1. **Token móvil** `POST /login/token/services.php/json/moodle_mobile_app` → **404**
   (servicio de token deshabilitado en el sitio).
2. **Login por sesión + curl** (`POST /login/index.php` con cookies jar) → redirige a
   `login/index.php?loginredirect=1` y muestra **"Acceso inválido. Por favor, intente otro"**
   → **la autenticación por curl fue rechazada** (la clave dada es la correcta en el navegador).
   Posibles causes a investigar: protección anti-bot/Cloudflare en el POST, 2FA, o falta de
   algún campo/referer. Dato útil: en la página de login el `sesskey` **NO** está en un input
   oculto, está en el JSON de config (`"sesskey":"W3K6QpYzCX"`).
3. El endpoint AJAX correcto para llamar WS con sesión es
   `/webservice/ajax/service.php?sesskey=<key>` con **body = array JSON**
   `[{"name":"…","args":{…}}, …]` (guionete en `p3_ws_smoke.ps1`, ya preparado con este
   formato; solo falta loguearse).

**Rutas para retomar (por orden de recomendación):**

- **(a) Smoke test por CLI (recomendado, no depende de HTTP):** script dentro de
  `moodle_app` con `CLI_SCRIPT` + `config.php`, `$USER = get_admin()` y luego
  `call_external_function('block_mwa_dashboard_get_grades_send_options', ['courseid'=>2,'groupid'=>0])`
  y `call_external_function('block_mwa_dashboard_get_grades_mail_history', ['courseid'=>2,'status'=>'','groupid'=>0,'limit'=>5,'offset'=>0])`
  (importar `lib/externallib.php`) → `var_export` de la respuesta. Es el mismo código de los WS
  (parámetros + lógica + capabilities con admin) sin la capa HTTP.
- **(b) Arreglar el login por curl** (UA de navegador + Referer, o probar admin/clave
  manualmente en el navegador para descartar 2FA) y reusar `p3_ws_smoke.ps1`.
- **(c) Validar en el E2E real del Paso 4** (el JS de la página llamará exactamente estos 2
  WS).

### 12.5 Hallazgos de entorno de esta sesión (NO perder)

| Hallazgo | Detalle |
| --- | --- |
| PowerShell/Posh-SSH | `Import-Module Posh-SSH` falla con el policy default (error de `.ps1xml`) → correr `powershell -NoProfile -ExecutionPolicy Bypass -File <script>`; el módulo **no se auto-importa** en la sesión del harness |
| `Set-SCPItem` v4.0 | **NO acepta array en `-Path`** (`CannotConvertArgument`) → iterar archivo por archivo |
| `Set-SCPItem` overwrite | **NO sobrescribe** el archivo remoto existente (comprobado: el host dejó el archivo viejo 2017 B con timestamp antiguo) → usar **nombre remoto nuevo en cada subida** (patrón `p3_*_vN.php`) |
| `rm` en `/tmp` del host | Los archivos SCP creados **no se pueden borrar** como `proyectosmym` (`Operation not permitted`) → limpieza pendiente (§12.6); dentro del contenedor `rm -f` funciona |
| Escape en PowerShell | `\` como continuación es bash (no PS); `\"` tampoco funciona → comandos en **una línea** o archivos `.ps1`/`.sh` subidos |
| Docker | `docker exec` sin `-u` = root (funciona); **usuario `moodle` no existe**; web = uid **1001**; owner original del plugin = `1000:1000` o `1001:0` (mixto, da igual por 644) |
| Moodle 5 CLI | `admin/cli/upgrade.php --non-interactive` (sin `-n/-v`); salida en español |
| Moodle 5 esquema | Sin `mdl_plugin` → versiones en `config_plugins`; `mdl_external_functions` sin columna `type`; filas como `stdClass` |
| BD real | `192.168.25.7` / `moodle_db` / `moodle_user`; **`capsu_mysql` NO es la BD de Moodle**; PDO crudo no sirve (§12.3) |
| Moodle CLI PHP | `/opt/bitnami/php/bin/php` 8.2.29 con `pdo_mysql` cargado |
| Sitio | Servicio de tokens WS móvil **404**; login por curl **rechazado**; `sesskey` en JSON de la página login |
| Curso de prueba | **id=2** · shortname `PRUEBA-RA` · "Aula de Prueba - Medición de Resultados de Aprendizaje" ✅ (existe y coincide) |

### 12.6 Limpieza pendiente (al retomar)

- Host `192.168.25.4` `/tmp/`: `p3_probe.php`, `p3_probe_v2..v4.php`, `p3_probe_v*.php`,
  `p3_diag_v1.sh`, `p3_grants_v1.sh`, `p3_tables_v1.php`, `p3_final_v*.php`, `p3_body.json`
  → borrar (probablemente requiera root: `sudo rm -f /tmp/p3_*` o desde otra sesión).
- Contenedor `moodle_app:/tmp/` → ya limpio (`rm -f` OK en todos los runs).
- **NO dejar** scripts temporales dentro de `blocks/mwa_dashboard/` (no se subió ninguno).
- Local: scripts `p3_*.ps1/.php/.sh` en `C:\Users\Christian\AppData\Local\Temp\opencode\`
  (reusables; `p3_ws_smoke.ps1` y `p3_final.php` sirven para retomar).

### 12.7 Próximos pasos (orden exacto al retomar la próxima sesión)

1. **Terminar Paso 3**: smoke test de los 2 WS por ruta (a) CLI recomendada (§12.4) sobre el
   curso id 2. *(usuario confirma)*
2. **Paso 4 — Frontend "Enviar notas"**: reescribir `amd/src/enviarnotas.js` (tabs, select de
   actividad, passmark, filtros 🟢🔴🟡+Todos, búsqueda, columna Resultado, modal centrado),
   regenerar `.min.js`, ampliar `styles/gradesmail.css`, **subir junto con `lang/es` +
   `lang/pt_br` + `enviarnotas.php` (59 claves)** → probar en curso id 2. *(usuario confirma)*
   > Nota: al subir la lang nueva, `gradesmail_confirm_send` pasa a `Send now` (label del
   > modal) — subir lang + JS nuevo **juntos** para no romper la confirmación (§11.3 ⚠️).
3. **Paso 5 — Pestaña "Historial"** (`get_grades_mail_history` → tabla, filtro, cargar más).
4. **Paso 6 — Modales** (confirm/alert centrados).
5. **Paso 7 — Cierre**: `php -l` + `node --check`, commit Git incremental, update de este plan.

---

## 13. Punto de corte — sesión 22-oct-2026 (Paso 3 CERRADO: smoke test CLI PASS)

**Estado: PASS/PASS en los 2 WS sobre curso id 2**, usando la ruta (a) recomendada
(§12.4): invocar directamente el método estático público del WS con `$USER = get_admin()`
dentro de `moodle_app`. Además, el smoke test **descubrió y forzó a corregir un bug real**
de `get_grades_mail_history` (LIMIT/OFFSET con parámetros nombrados → error de sintaxis
MariaDB). Verificación final con sha256 de `classes/external.php` = local.

> **Decisión de diseño (reutilizar en adelante):** en Moodle 5 **NO existe** la función
> global `call_external_function()` (la receta literal de §12.4(a) falla). El equivalente
> funcional de "llamar un WS sin la capa HTTP" es invocar el método estático público de la
> clase del WS directamente (mismo `validate_parameters` + lógica + capabilities):
> `\block_mwa_dashboard\external::get_grades_send_options(2, 0)`.
> `core\external\external_api::call_external_function()` **sí existe** pero exige sesión
> web (isloggedin + sesskey), por lo que **NO sirve para CLI**.

### 13.1 Resultados (curso id 2 — PRUEBA-RA)

- [x] **`get_grades_send_options {courseid:2, groupid:0}`** → PASS. Devuelve
      `activities:[{seq:1, cmid:2, name:"Prueba funcional - Momento 1 RA", modname:"quiz",
      grademax:10, passmark:6, hascustompassmark:true}]`. (passmark explícito 6/10, no el
      fallback 50%).
- [x] **`get_grades_mail_history {courseid:2, status:"", groupid:0, limit:5, offset:0}`**
      → PASS. `total:9`, 5 entradas con campos correctos (`date`, `time`, `teachername`,
      `studentname`, `status:"sent"`, `send_type:"both"`, `subject`, `moodlelinked:true`).
- [x] **Contexto `get_grades` (WS existente)**: 7 estudiantes matriculados (3 con nota: 7, 3,
      y una; 1 sin nota), 1 actividad gradeable (seq 1 = act_1) — **alineado con el `seq`**
      del WS de opciones.
- (Los criterios runtime de `send_grades_mail` y `get_grades_mail_preview` quedan cubiertos
      por la E2E real del Paso 4, ya que el JS nuevo llamará exactamente esos WS.)

### 13.2 Bug real corregido (fuerza a actualizar el backend desplegado)

- **Síntoma:** `get_grades_mail_history` → `dml_read_exception: … syntax … near ''5' OFFSET '0''`.
- **Causa:** `get_records_sql('… LIMIT :lim OFFSET :off', …)` — el driver mysqli cita los
  parámetros LIMIT/OFFSET como cadenas y MariaDB rechaza `'OFFSET '0''`.
- **Fix (aplicado en `classes/external.php`, ~L1369):** inline de enteros validados
  (`' LIMIT ' . $limit . ' OFFSET ' . $offset`, `$limit`/`$offset` ya son int) + comentario
  `// NOTE: LIMIT-OFFSET…` explicando el porqué. Regla para el plugin: **nunca** usar
  parámetros nombrados en `LIMIT`/`OFFSET`.
- **Verificado:** `php -l` OK; redeployado al servidor y **sha256 del servidor = local**
  (`5e9513a8…bee21b`); smoke test de `get_grades_mail_history` pasó tras el fix (fallaba antes).
- **Importante:** esto es un **cambio de backend real** sobre la v1 desplegada (v1 no tenía
  estos WS). El servidor ahora tiene `external.php` con el fix (sha256 confirmado);
  `services.php`/`version.php` ya estaban en 2026102201.

### 13.3 Limpieza adicional (sumar a §12.6)

- Host `/tmp`: quedaron `p3_smoke_ws_v2.php`, `p3_ext_fix_v1.php` (y versiones previas)
  → `rm` como `proyectosmym` → **Operation not permitted** (mismo que §12.6; borrar como
  root: `sudo rm -f /tmp/p3_*`).
- Contenedor `moodle_app:/tmp`: `p3_smoke_ws_v1.php` NO se pudo borrar como uid
  (sticky-bit) → borrar con `docker exec -u root moodle_app rm -f`.

### 13.4 Hallazgos de entorno adicionales (adicionar a §12.5)

| Hallazgo | Detalle |
| --- | --- |
| Moodle 5 sin `call_external_function()` global | Ruta CLI recomendada (y usada): método estático público de la clase del WS + `global $USER; $USER = get_admin();`. `core\external\external_api::call_external_function()` exige sesión web → no sirve CLI |
| `docker exec` sin `-u` = uid 1001 | No puede borrar archivos de otros usuarios en `/tmp` (sticky-bit) → usar `docker exec -u root` para `rm` |
| PowerShell: `"` dentro de string `"…"` | Se escapa con backtick `` `" ``, **no** con `\` (un `\` es carácter literal en PS) |
| Bug mysqli LIMIT/OFFSET | Parámetros nombrados citados como string → error MariaDB. Usar enteros inline validados |
| Curso id 2 (datos reales) | 7 estudiantes, 1 quiz gradeable (cmid 2, máx 10, pass 6), 9 envíos históricos de auditoría |

### 13.5 Siguiente — Paso 4 (frontend «Enviar notas»)

Reescribir `amd/src/enviarnotas.js` (tabs Enviar/Historial, select de actividad, campo
passmark, filtros 🟢🔴🟡+Todos, búsqueda, columna Resultado, modal centrado), regenerar
`.min.js`, ampliar `styles/gradesmail.css`, **subir junto con `lang/es` + `lang/pt_br` +
`enviarnotas.php` (59 claves)** → probar en curso id 2.
⚠️ No separar la subida de lang del JS: `gradesmail_confirm_send` pasa a `Send now`
(label del modal) y el JS viejo la usaba como texto de `window.confirm` (§11.3 ⚠️).

---

## 14. Punto de corte — sesión 22-oct-2026 (Paso 4 — EN CURSO: investigación completa, código pendiente)

**Estado: Paso 4 APROBADO por el usuario («Arranca Paso 4»). Investigación y diseño
COMPLETOS; el código del JS v2 NO se escribió aún** (la sesión se cortó justo antes del
`write` de `amd/src/enviarnotas.js`). Todo lo investigado queda aquí para retomar sin
re-leer archivos.

### 14.1 Verificaciones hechas antes de escribir el v2

- [x] `amd/src/enviarnotas.js` v1 (15,073 B, 386 líneas) leído completo → base para el v2
      (se conserva: `callWS` con `Ajax.call([...])[0]` (§11.9), `t()`, `esc()`, `fmt()`,
      `isnum()`, `gradeCell()`, `nameOf()`, `channelMark()`, `renderResults()`, patrón
      `els.wrap.innerHTML` + `bindEls()`).
- [x] `lang/en` (líneas 2151-2206), `lang/es` (56-103) y `lang/pt_br` (2162-2209): las 37
      strings nuevas existen y coinciden con las 35 claves extra de `$gmkeys`
      (las otras 2 son `button`/`page_desc`/`jsnotice` de PHP). Placeholders de
      `confirm_body`: `{count}`, `{activity}`, `{passnote}`.
- [x] `classes/api.php` (L1047-1080): fila de estudiante trae `act_{seq}` con **`'-'`
      (dash) cuando no hay nota** → `isnum()` del v2 debe tratar `'-'` como no-numérico
      (el v1 no lo hacía; solo afectaba al total, ahora afecta también a los filtros).
- [x] `styles/gradesmail.css` v1 (5,071 B, 277 líneas) leído completo → el v2 solo
      **agrega** clases (no rompe las existentes).
- [x] `enviarnotas.php` NO requiere cambios: sus 59 claves cubren todo lo que usará el
      v2 (verificado: tab_*, evalselect, passmin*, filter_*, searchplaceholder,
      result*, hist_*, col_*, confirm_*, alert_noselection, error_*). La string v1
      `noselection` queda sin uso (el v2 usa `alert_noselection`).
- [x] `lang/es` y `lang/pt_br` listos en local (solo falta SUBIRLOS con el JS).

### 14.2 Decisiones de diseño del v2 (cerradas; no re-discutir)

1. **Módulo**: sigue siendo `define(['core/ajax'])` + ES5 (`var`, `function`) como todo el
   plugin; sin `core/webservices` (§11.9).
2. **Estado global**: `students[]` ({userid, name, total, totalmax, acts:{seq:valor}}),
   `meta[]` (del WS send_options), `selSeq`, `filter` ('all'|'passed'|'failed'|'pending'),
   `searchText`, `rowEls{uid→{tr,box,badge}}`, flags de historial (`histStarted/Offset/Total/State`).
3. **Carga doble**: `get_grades` + `get_grades_send_options` en paralelo; la UI se arma
   cuando ambas terminan (flags `studentsReady`/`metaReady` + `checkReady()`).
4. **Select de actividad**: solo lista las actividades del WS; vacío+disabled si no hay.
   Al cambiar → precarga passmark (`custom`/`fallback`), re-clasifica y resetea filtro a
   'Todos' (documentado: no toca la selección).
5. **Passmark**: precargado del WS (`passmark`/`hascustompassmark`); hint según estado
   (`passmin_custom`/`passmin_fallback`/`passmin_none`); si `grademax=0` → campo vacío
   habilitado (se define manual) con hint `passmin_none`. Sin actividad → disabled.
6. **Filtros 🟢🔴🟡** (decisión §11.7-2(a)): muestran SOLO ese grupo Y marcan sus
   checkboxes (deseleccionando el resto); **'Todos'** restaura la vista sin tocar la
   selección; se re-aplican automáticamente al cambiar passmark/actividad. Chips
   disabled cuando passmark≤0 (sin clasificación posible). Punto pendiente de decisión:
   semántica de "Seleccionar todos" con filtro activo → **decisión tomada: opera sobre
   las filas visibles** (filtro ∩ búsqueda).
7. **Búsqueda por nombre**: solo afecta visibilidad (no toca checkboxes); case-insensitive.
8. **Columna "Resultado de la actividad"**: badge (`mwa-gm-badge-passed/failed/pending`)
   recalculado en cada cambio de actividad/passmark; sin actividad o sin passmark →
   dash neutro (`mwa-gm-dash`, sin string nueva).
9. **Modal centrado** (reemplaza `window.confirm`/`alert`): un único `<div>` creado lazy
   y agregado al root después de `buildUI()`; backdrop + diálogo; botones
   `confirm_cancel` (sec) y `confirm_send` (primary, label); Escape y backdrop cierran;
   foco en el botón primario (o en cancel si es solo alert). Confirmación usa
   `confirm_body` con `{count}` + `{activity}` (nombre, o `confirm_noactivity` si no hay
   actividad) + `{passnote}` = `confirm_passnote` solo si passmark>0. Alerts
   (sin selección / error WS) usan `error_title` + `alert_noselection`/`error_generic`.
10. **Historial** (pestaña, carga perezosa al primer switch): WS
    `get_grades_mail_history` (limit 25, offset creciente), filtro de estado
    (hist_state_all/sent/error), "Cargar más" visible si `offset < total`, empty state
    `hist_empty`; fila = fecha/hora formateadas con `Intl.DateTimeFormat(undefined, …)`
    desde `timesent` (epoch) — el `date`/`time` del server en ES vienen partidos raro
    ("22" / "de octubre de 2026, 07:48", ver §13.1 ⚠️), docente, estudiante, badge estado
    (`hist_state_sent`/`hist_bounced`), asunto (word-break).
11. **Estilo**: conservar `mwa-gm-*`; nuevos: tabs (`mwa-gm-tabs/tab/is-active`),
    chips con punto de color por CSS (`::before`), select/input, hint, badges de
    resultado, dash, modal (fixed + backdrop + box), historial.
12. **Minificado**: a mano (sin Grunt, mismo criterio que las otras 14) →
    `amd/build/enviarnotas.min.js`; verificar con `node --check` (Node v24 disponible).

### 14.3 Lo que queda del Paso 4 (orden exacto al retomar)

1. **Escribir `amd/src/enviarnotas.js` (v2)** — el único archivo NO creado aún; diseño
   completo en §14.2 y base v1 ya leída (§14.1).
2. `node --check` en el src.
3. Minificar a mano → `amd/build/enviarnotas.min.js` + `node --check`.
4. `styles/gradesmail.css`: **agregar** (no reemplazar) los estilos nuevos de §14.2-11.
5. `php -l` en `enviarnotas.php` + 3 `lang` (por seguridad; no se tocaron).
6. **Deploy TODO JUNTO** (riesgo §11.3): `amd/src/enviarnotas.js`,
   `amd/build/enviarnotas.min.js`, `styles/gradesmail.css`, `enviarnotas.php` (59 keys),
   `lang/en`, `lang/es`, `lang/pt_br` → `/tmp` (nombre nuevo por archivo) → `docker cp`
   a sus rutas en `moodle_app:/bitnami/moodle/blocks/mwa_dashboard/`. (Patrón §12.5:
   SCP no sobrescribe → nombre nuevo; `docker cp` sí sobrescribe.)
7. Probar en curso **id=2** con navegador (admin): pestaña Enviar (lista 7 estudiantes,
   select quiz, passmark 6, filtros 🟢🔴🟡 sobre datos reales (3 con nota), búsqueda,
   preview, modal, envío a 1 estudiante) y pestaña Historial (9 envíos reales, filtro
   estado, cargar más). *(usuario confirma)*
8. Commit incremental + update de este plan (§14 → ✅ y Paso 4 cerrado).
9. Luego: Paso 5 (probar/afinar Historial ya incluido en el v2), Paso 6 (modales ya
   incluidos en el v2 — verificarlos), Paso 7 (cierre: php -l + node --check +
   changelog README/ESTRUCTURA si queda tiempo).

### 14.4 Estado del despliegue (contraste local ↔ producción)

| Archivo | Local | Servidor (producción) |
| --- | --- | --- |
| `classes/external.php` | fix LIMIT/OFFSET (sha256 `5e9513a8…bee21b`) | ✅ igual (subido en §13.2) |
| `db/services.php`, `version.php` | 2 WS + 2026102201 | ✅ (Paso 3, §12.1) |
| `amd/src/enviarnotas.js` | v1 (15,073 B) | v1 (15,073 B) |
| `amd/build/enviarnotas.min.js` | v1 (8,081 B) | v1 (8,081 B) + `.bak` residual |
| `styles/gradesmail.css` | v1 (5,071 B) | v1 |
| `enviarnotas.php` | 59 claves | v1 (24 claves) |
| `lang/en` | completo | completo (§12.1 sin subir lang nueva: verificar) |
| `lang/es`, `lang/pt_br` | completo (+37) | v1 (sin las 37 nuevas) |


