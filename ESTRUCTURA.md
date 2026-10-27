# Estructura del plugin `block_mwa_dashboard`

> **MWA Analytics Dashboard** — bloque de Moodle para que los docentes monitoren participación, asistencia, calificaciones, alertas, intervenciones pedagógicas y seguimiento de estudiantes dentro de un curso.
>
> | Dato | Valor |
> | --- | --- |
> | Tipo de plugin | **Block** |
> | Componente | `block_mwa_dashboard` |
> | Directorio de instalación | `blocks/mwa_dashboard` |
> | Versión actual | `2026102601` (release **4.3.1** — Grades email) |
> | Requisito mínimo | Moodle 4.1+ (`$plugin->requires = 2022112800`), PHP 7.4+ |
> | Autor | Bruno Porto (IFES/CEFOR) |
> | Licencia | GNU GPL v3 (código libre, modificable) |

---

## 1. Mapa de archivos

```
moodle-block_mwa_dashboard/
├── block_mwa_dashboard.php      ← Clase del bloque (el "widget" de la sidebar)
├── view.php                     ← Página principal del dashboard completo
├── track.php                    ← Endpoint POST para activar/desactivar el tracking por actividad
├── edit_form.php                ← Formulario de configuración del bloque (casi vacío)
├── settings.php                 ← Ajustes de administración (enlaza a configaikey.php)
├── configaikey.php              ← Página de administración: proveedor IA + credenciales
├── testai.php                   ← Endpoint de prueba de conexión con el proveedor IA
├── version.php                  ← Versión, requisito y dependencias del plugin
├── README.md                    ← Documentación de usuario
├── PRIVACY.md                   ← Descubrimiento de privacidad (GDPR / privacy policy)
├── LICENSE                      ← GPL v3
│
├── amd/
│   ├── src/                     ← ⭐ CÓDIGO FUENTE JS (edítalo aquí)
│   │   ├── dashboard.js         ← Punto de entrada (init) desde view.php
│   │   ├── dashboardapp.js      ← Shell/orquestador de la UI
│   │   ├── dashboardstore.js    ← Estado compartido (almacén tipo "store")
│   │   ├── actioncenter.js      ← Tarjeta "Action Center" / KPIs de la clase
│   │   ├── alerts.js            ← Módulo de alertas
│   │   ├── classlist.js         ← Lista de estudiantes + tarjeta suspendida
│   │   ├── studentprofile.js    ← Perfil individual del estudiante
│   │   ├── activities.js        ← Análisis de actividades/recursos
│   │   ├── grades.js            ← Módulo de calificaciones
│   │   ├── interventions.js     ← Registro de intervenciones
│   │   ├── teacherfeedback.js   ← Retroalimentación docente
│   │   ├── chat.js              ← Chat IA con la clase
│   │   ├── engagementcalc.js    ← Cálculo de situación/engagement
│   │   └── adminai.js           ← UI de la página configaikey.php
│   └── build/                   ← Archivos .min.js compilados (NO editar a mano)
│
├── classes/
│   ├── observer.php             ← Escucha eventos de Moodle → escribe en tablas del plugin
│   ├── external.php             ← ⭐ API web services (todas las funciones públicas)
│   ├── content_extractor.php    ← Extrae texto de actividades para el análisis IA
│   ├── snapshot_manager.php     ← Crea/lee snapshots inmutables de intervenciones
│   ├── api.php                  ← Clases auxiliares de la API
│   ├── ai/
│   │   ├── client.php           ← Cliente HTTP del proveedor IA (DeepSeek/OpenAI/Gemini/Anthropic/OpenRouter/institucional)
│   │   └── audit.php            ← Auditoría (solo metadatos) de cada llamada IA
│   ├── output/
│   │   ├── dashboard_page.php   ← Colecciona datos + `export_for_amd()` y `require_assets()`
│   │   └── renderer.php         ← Renderer Mustache del plugin
│   ├── task/
│   │   └── privacy_cleanup.php  ← Cron: limpieza de datos por política de privacidad
│   └── privacy/
│       └── provider.php         ← Implementa el provider de privacidad de Moodle
│
├── db/
│   ├── install.xml              ← Esquema XMLDB (5 tablas)
│   ├── upgrade.php              ← ⭐ Migraciones paso a paso (xmldb_block_mwa_dashboard_upgrade)
│   ├── events.php               ← Lista de eventos de Moodle que se capturan (observers)
│   ├── access.php               ← Capabilities/permisos del plugin
│   ├── services.php             ← Registro de funciones web services (AJAX)
│   ├── tasks.php                ← Tareas cron (privacy_cleanup a las 3:00)
│   └── messages.php             ✅ (registro del provider de mensajes del bloque)
│
├── templates/                   ← Plantillas Mustache (HTML del dashboard)
│   ├── dashboard_page.mustache  ← Contenedor principal
│   ├── classlist_detail_modal.mustache
│   ├── intervention_detail_modal.mustache
│   ├── student_profile.mustache
│   ├── student_calendar.mustache
│   ├── empty_state.mustache
│   ├── chat_*.mustache          ← UI del chat IA (welcome, sidebar, chips, mensajes, typing)
│   ├── action_ai_placeholder.mustache
│   ├── action_retention_drill.mustache
│   └── content_inline_*.mustache
│
├── styles/                      ← Hojas de estilo por módulo
│   ├── dashboard.css            ← Estilos base
│   ├── actioncenter.css, alerts.css, classlist.css, grades.css,
│   ├── activities.css, interventions.css, heatmap.css, chat.css,
│   ├── studentprofile.css, teacherfeedback.css,
│   ├── filter-polish.css, kpi-polish.css
│
├── pix/
│   ├── logo.png                 ← Logo visible en el bloque
│   └── enviando-email.gif       ← Animación al enviar mensaje
│
├── lang/
│   ├── en/block_mwa_dashboard.php   ← ⭐ Todas las cadenas de texto (inglés)
│   └── pt_br/block_mwa_dashboard.php        ← Traducción al portugués brasileño
│
└── tests/                       ← Pruebas PHPUnit automatizadas
    ├── ai_audit_test.php
    ├── ai_privacy_test.php
    ├── ai_submission_leak_test.php
    └── ai_allowlist_test.php
```

---

## 2. Flujo general de la aplicación

```
┌──────────────────────────────────────────────────────────────────────────┐
│  MOODLE CORE (escribe logstore_standard_log)                            │
└──────────────────┬───────────────────────────────────────────────────────┘
                   │ (eventos: module_viewed, submission, post_created, …)
                   ▼
   db/events.php  ─────────►  classes/observer.php::store()
                                      │
                                       ▼
                  block_mwa_dashboard_log   (tabla propia del plugin,
                  block_mwa_dashboard_course (conmuto por curso)
                  ┌─────────────────────────────────────────────────────┐
                  │  view.php (abre el dashboard)                      │
                  │    └─► classes/output/dashboard_page.php           │
                  │          · lee logs, grados, intervenciones        │
                  │          · export_for_amd() → datos iniciales      │
                  │          · require_assets() → CSS + JS             │
                  │    └─► templates/dashboard_page.mustache           │
                  │    └─► amd/src/dashboard.js (init)                 │
                  │          ├─► dashboardapp.js (shell de la UI)      │
                  │          ├─► actioncenter.js  ┐                    │
                  │          ├─► alerts.js        │  cada módulo       │
                  │          ├─► classlist.js     │  llama a la API    │
                  │          ├─► grades.js        │  con ajax()        │
                  │          ├─► interventions.js │  a servicios       │
                  │          ├─► studentprofile.js├─  registrados en   │
                  │          └─► chat.js          ┘  db/services.php   │
                  │                                                     │
                  │  Web services (block_mwa_dashboard.*)              │
                  │    └► classes/external.php::método()               │
                  │         └► consultas SQL a tablas del plugin/      │
                  │             graderoom/core y respuestas JSON       │
                  │                                                     │
                  │  (Opcional) IA:                                     │
                  │    amd chat.js ──► external::chat_message()        │
                  │                 ──► ai/client.php::send()          │
                  │                 ──► ai/audit.php (metadatos)        │
                  │                 ──► proveedor externo              │
                  └─────────────────────────────────────────────────────┘
```

**Punto clave:** el plugin no lee `logstore_standard_log` directamente en tiempo de lectura. Escribe en su **propia tabla** (`block_mwa_dashboard_log`) por medio de *observers* de eventos, lo que hace el dashboard rápido y predecible.

---

## 3. Tablas de base de datos (`db/install.xml`)

| Tabla | Propósito | Campos principales |
| --- | --- | --- |
| `block_mwa_dashboard_log` | Registro ligero de eventos capturados | `courseid`, `userid`, `component`, `action`, `contextinstanceid` (cmid), `timecreated`, `origin` |
| `block_mwa_dashboard_messages` | Intervenciones / mensajes de docentes a estudiantes | `courseid`, `userid` (destinatario), `teacherid`, `subject`, `message`, `timesent`, `status`, `ai_generated`, `intervention_reason`, `send_type` (moodle/email), `target_type`, `target_items` (JSON), `teacher_note`, `teacher_note_updated`, `moodle_msgid` |
| `block_mwa_dashboard_snapshot` | Snapshot inmutable de la situación del estudiante al momento de una intervención | `interventionid` (→ messages), `courseid`, `userid`, `reason`, `situation`, `actiontaken`, `objective`, `snapshotdata` (JSON) |
| `block_mwa_dashboard_aiaudit` | Auditoría de solo metadatos de llamadas IA (nunca prompts ni PII) | `userid`, `courseid`, `operation`, `purpose`, `provider`, `endpoint`, `categories`, `status`, `timecreated` |
| `block_mwa_dashboard_course` | Control de captura por curso + exclusiones de actividades | `courseid`, `enabled` (0/1), `excludedcmids` (JSON), `timemodified` |

> **Para añadir una columna o tabla:** edita `db/install.xml` **y** añade un paso `if ($oldversion < X)` en `db/upgrade.php` + incrementa `version.php`.

---

## 4. Capabilities (`db/access.php`)

| Capability | Lect/Escr | Quién lo tiene por defecto |
| --- | --- | --- |
| `block/mwa_dashboard:view` | read | teacher, editingteacher, manager |
| `block/mwa_dashboard:useai` | read (RISK_PERSONAL) | teacher, editingteacher, manager |
| `block/mwa_dashboard:manageinterventions` | write (RISK_PERSONAL, RISK_SPAM) | teacher, editingteacher, manager |
| `block/mwa_dashboard:managecapture` | write (RISK_CONFIG) | editingteacher, manager |
| `block/mwa_dashboard:addinstance` | write | editingteacher, manager |
| `block/mwa_dashboard:myaddinstance` | write | user |

---

## 5. API pública (web services, `db/services.php` + `classes/external.php`)

| Servicio | Método | Tipo | Descripción |
| --- | --- | --- | --- |
| `block_mwa_dashboard_get_logs` | `external::get_logs` | read | Logs de acceso de un curso |
| `block_mwa_dashboard_get_grades` | `external::get_grades` | read | Notas de todos los estudiantes |
| `block_mwa_dashboard_send_message` | `external::send_message` | **write** | Enviar intervención al estudiante |
| `block_mwa_dashboard_get_interventions` | `external::get_interventions` | read | Historial de intervenciones |
| `block_mwa_dashboard_get_followup_indicators` | `external::get_followup_indicators` | read | Indicadores actuales vs. snapshot |
| `block_mwa_dashboard_delete_intervention` | `external::delete_intervention` | **write** | Borrar una intervención |
| `block_mwa_dashboard_save_intervention_note` | `external::save_intervention_note` | **write** | Guardar nota privada del docente |
| `block_mwa_dashboard_get_ai_recommendation` | `external::get_ai_recommendation` | read | Recomendación IA sobre un estudiante/clase |
| `block_mwa_dashboard_chat_message` | `external::chat_message` | read | Mensaje al chat IA |
| `block_mwa_dashboard_get_activity_content` | `external::get_activity_content` | read | Extraer contenido de una actividad |
| `block_mwa_dashboard_get_due_dates` | `external::get_due_dates` | read | Fechas límite por cmid |
| `block_mwa_dashboard_set_activity_tracking` | `external::set_activity_tracking` | **write** | Incluir/excluir actividad del rastreo |
| `block_mwa_dashboard_get_hidden_activities` | `external::get_hidden_activities` | read | Actividades ocultas (visual) del docente |
| `block_mwa_dashboard_set_activity_hidden` | `external::set_activity_hidden` | **write** | Mostrar/ocultar actividad |
| `block_mwa_dashboard_get_grades_mail_preview` | `external::get_grades_mail_preview` | read | Preview exacta del correo de notas (subject, html, plain, studentname) |
| `block_mwa_dashboard_send_grades_mail` | `external::send_grades_mail` | **write** | Enviar notas a 1+ estudiantes (email + mensaje Moodle + auditoría) |
| `block_mwa_dashboard_get_grades_send_options` | `external::get_grades_send_options` | read | Actividades con grademax/passmark para select, filtros y clasificación |
| `block_mwa_dashboard_get_grades_mail_history` | `external::get_grades_mail_history` | read | Historial de envíos de notas (auditoría) con límite/desplazamiento y filtro de estado |
| `block_mwa_dashboard_delete_grades_mail_record` | `external::delete_grades_mail_record` | **write** | Borrar 1 registro de envío de notas (solo `intervention_reason='Grades'`) |
| `block_mwa_dashboard_clear_grades_mail_history` | `external::clear_grades_mail_history` | **write** | Limpiar el historial de envíos de un curso(+grupo) (solo 'Grades') |

> **Para hacer una función nueva:** `external::mi_funcion()` + `_parameters()` + `_returns()` en `classes/external.php`, registrarla en `db/services.php`, y llamarla desde `amd/src/*.js` con `require(['core/webservice'], ...)`.

---

## 6. Captura de eventos (`db/events.php`)

Se escucha estos eventos del core/actividades y todos llaman a `observer::store()`:

| Evento | Módulo |
| --- | --- |
| `core\event\course_module_viewed` | Todas las actividades (forum, assign, quiz, page, book…) |
| `core\event\course_module_completion_updated` | Completitud |
| `mod_assign\event\assessable_submitted` | Tareas |
| `mod_quiz\event\attempt_submitted` | Quiz |
| `mod_quiz\event\attempt_started` | Quiz |
| `mod_forum\event\post_created` | Foro |
| `mod_forum\event\discussion_created` | Foro |
| `mod_hvp\event\course_module_viewed` / `attempt_submitted` | H5P |
| `mod_scorm\event\sco_launched` | SCORM |
| `core\event\user_graded` | Calificaciones |

> **Para añadir un evento nuevo:** registra la línea en `db/events.php`. Si necesitas campos nuevos, amplía `db/install.xml` + `db/upgrade.php` y actualiza `observer::store()`.

---

## 7. Módulo IA opcional (configuración y flujo)

| Archivo | Rol |
| --- | --- |
| `configaikey.php` | Página de admin: proveedor, modelo, credencial, test de conexión |
| `classes/ai/client.php` | Cliente HTTP; soporta **DeepSeek, OpenAI, Gemini, Anthropic, OpenRouter, institucional** |
| `classes/ai/audit.php` | Escribe en `block_mwa_dashboard_aiaudit` solo metadatos (no prompts ni PII) |
| `testai.php` | Endpoint `?action=test` para comprobar el proveedor |

**Config clave (`get_config` en el componente, nombrada `block_mwa_dashboard`):**

| Clave | Propósito |
| --- | --- |
| `ia_provider` | `deepseek` / `openai` / `gemini` / `anthropic` / `openrouter` / `institutional` (o vacío = desactivado) |
| `ia_model` | Modelo concreto o `recommended` |
| `ia_provider_credential` | API key del proveedor (nunca expuesta al JS) |
| `ia_institutional_private` | Opt-in para proveedor institucional |
| `ia_institutional_credential` | Credencial institucional |
| `ia_credential_lastrotated` | Timestamp de última rotación |

> **Flujo de protección:** capability `useai` → filtro de campos permitidos → pseudo-anonimización de usuarios → filtro final (emails, IPs, enrollment ids, contenido de submissions) → llamada al proveedor → `audit.php` escribe solo metadatos.

---

## 8. Cron / tareas programadas (`db/tasks.php`)

| Classname | Schedule | Rol |
| --- | --- | --- |
| `\block_mwa_dashboard\task\privacy_cleanup` | `3am` todos los días (cron `R 3 * * *`) | Limpieza de datos según política de privacidad |

---

## 9. Proveedor de mensajes de Moodle

El plugin se registra como **provider de bloques de mensajes** (`db/messages.php`). Los estudiantes reciben las intervenciones por la bandeja de mensajes de Moodle (`send_type='moodle'`) **o** por correo electrónico (`send_type='email'`). El upgrade `2026081510` re-ejecuta `message_update_providers('block_mwa_dashboard')` para forzar el provider `block` (evita que las intervenciones caigan en el procesador de email del core).

---

## 10. Plantillas Mustache (UI)

| Plantilla | Módulo |
| --- | --- |
| `dashboard_page.mustache` | Contenedor principal |
| `classlist_detail_modal.mustache` | Tarjeta suspendida del estudiante |
| `intervention_detail_modal.mustache` | Tarjeta suspendida de la intervención |
| `student_profile.mustache` | Perfil individual completo |
| `student_calendar.mustache` | Calendario de acceso |
| `empty_state.mustache` | Estados vacíos |
| `chat_*.mustache` | UI del chat IA (sidebar, chips, mensajes, welcome, typing) |
| `action_*.mustache` | Acciones de la Action Center |
| `content_inline_*.mustache` | Contenido inline |

> Los datos llegan desde `dashboard_page.php` (`export_for_amd()`) a los JS, y las plantillas se renderizan con `renderer.php` / `OUTPUT->render_from_template(...)`.

---

## 11. CSS por módulo (`styles/`)

| Archivo | Área |
| --- | --- |
| `dashboard.css` | Base/layout |
| `actioncenter.css` | Action Center |
| `alerts.css` | Alertas |
| `classlist.css` | Lista de estudiantes |
| `studentprofile.css` | Perfil individual |
| `grades.css` | Calificaciones |
| `gradesmail.css` | Módulo "Enviar notas" (`enviarnotas.php`) |
| `activities.css` | Actividades/recursos |
| `interventions.css` | Intervenciones |
| `heatmap.css` | Mapa de calor de acceso |
| `chat.css` | Chat IA |
| `teacherfeedback.css` | Retroalimentación |
| `filter-polish.css` / `kpi-polish.css` | Ajustes visuales de filtros y KPIs |

> Todos los CSS se registran en `dashboard_page::require_assets($PAGE)` con `css()` apuntando a `/blocks/mwa_dashboard/styles/<nombre>.css`.

---

## 12. Lenguaje (strings)

Todos los textos visibles van en `lang/<idioma>/block_mwa_dashboard.php` como `$string['clave'] = 'texto';`. Los JS usan `require(['core/str'], ...)` para traducir sin hardcodear. **No edites strings en el código PHP.**

---

## 13. Puntos de referencia rápida para modificar

| Quiero… | Archivo(s) |
| --- | --- |
| Cambiar el texto visible | `lang/en/block_mwa_dashboard.php` (y/o `pt_br`) |
| Añadir/quitar funcionalidad JS | `amd/src/*.js` + (si aplica) `templates/*.mustache` + `styles/*.css` → **recompilar con Grunt** (`grunt amd`) |
| Cambiar el comportamiento del API | `classes/external.php` + `db/services.php` (registrar) + JS que lo consume |
| Añadir una tabla/columna | `db/install.xml` + paso en `db/upgrade.php` + `version.php` (incrementar) |
| Capturar un evento nuevo | `db/events.php` + (si hace falta campos) `db/install.xml` + `db/upgrade.php` + `observer::store()` |
| Cambiar el proveedor IA | `configaikey.php` + `classes/ai/client.php` |
| Cambiar el logo del bloque | `pix/logo.png` |
| Cambiar permisos por rol | `db/access.php` + (si hace falta) migración en `db/upgrade.php` |
| Desactivar/limpiar datos de un curso | `track.php` / `configaikey.php` / SQL directo en `{block_mwa_dashboard_log}` |
| Revisar la migración | `db/upgrade.php` (función `xmldb_block_mwa_dashboard_upgrade`) |
| Probar en tests | `tests/*.php` (PHPUnit) |
| Modificar "Enviar notas" | `enviarnotas.php` + `amd/src/enviarnotas.js` + `classes/external.php` (sección grades mail) + `styles/gradesmail.css` + `lang/*` |

---

## 14. Checklist antes de desplegar cambios

- [ ] `$plugin->version` en `version.php` **está incrementado** (formato `YYYYMMDDxx`, ej. `2026082690`).
- [ ] Si hay cambios de esquema, hay un paso en `db/upgrade.php` con `upgrade_block_savepoint(true, X, 'mwa_dashboard')`.
- [ ] Los archivos `amd/build/*.min.js` fueron **recompilados** (no editados a mano) con `grunt amd` o `grunt dev` para desarrollo local.
- [ ] Los strings nuevos están en `lang/en/` (y `pt_br` si aplica) con `get_string('clave', 'block_mwa_dashboard')`.
- [ ] `php -l` pasa sobre todos los archivos PHP modificados (sintaxis limpia).
- [ ] Capabilities nuevas (si las hay) están en `db/access.php` y asignadas por upgrade.
- [ ] Tests del paquete `tests/` siguen pasando: `vendor/bin/phpunit --filter block_mwa_dashboard` (si hay vendor).

---

## 15. Módulo "Enviar notas por correo" (v4.3.1, desde 2026102601)

Módulo de página propia (no dentro del monolito `dashboardapp.js`) que permite al docente
seleccionar estudiantes, previsualizar y enviar sus notas por correo + mensaje de Moodle,
con historial y auditoría.

| Archivo | Rol |
| --- | --- |
| `enviarnotas.php` | Página protegida (`require_login` + caps `view` + `manageinterventions`); define `$gmkeys[]` (claves que llegan al JS vía `js_call_amd`) |
| `block_mwa_dashboard.php` | Botón "Enviar notas" en el bloque (solo con `manageinterventions`, curso id > 1) |
| `amd/src/enviarnotas.js` | UI completa: tabs Enviar/Historial, select de actividad, passmark, filtros rápida, búsqueda por nombre, chips de estudiantes, edición del comentario en el preview (T1), envío, resultados, borrar/limpiar historial (T3b) |
| `amd/build/enviarnotas.min.js` | Minificado (a mano, sin Grunt en este repo — verificación de markers antes de cada deploy) |
| `styles/gradesmail.css` | Estilos `mwa-gm-*` del módulo |
| `classes/external.php` | Sección "grades mail": `build_grades_mail()` (fuente común de preview y envío, HTML inline-safe) + los 8 WS `*_grades*` de la tabla §5 |
| `tests/send_grades_mail_test.php` | 13 tests: capabilities, lotes, anti-fuga entre estudiantes, anti-manipulación del comentario, estilos inline, delete/clear de historial |
| `lang/en|es|pt_br` | Strings `gradesmail_*` (UI + cuerpo del correo) |

**Reglas no negociables del diseño:**

- 1 correo + 1 mensaje Moodle por estudiante (nunca correo de grupo).
- Solo ítems `hidden=0`; datos de `api::get_grades()` como fuente única.
- **WYSIWYG**: preview y envío construyen el mismo HTML con `build_grades_mail()`; el
  ancla `data-gm-editable` permite que solo el párrafo de comentario sea editable en vivo
  y la única variable libre del POST sigue siendo `comment`.
- Correo en multipart (plain+html) con estilos **inline** (tabla con bordes, cabecera de
  fondo, colores pasadas/pendientes): seguro en Gmail/Outlook web.
- Auditoría: 1 fila en `block_mwa_dashboard_messages` por envío, `intervention_reason='Grades'`;
  el borrado (`delete_grades_mail_record` / `clear_grades_mail_history`) **nunca** toca
  intervenciones pedagógicas (verificación dura `intervention_reason='Grades'`).
