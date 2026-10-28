# PLAN — Sesión siguiente: block_mwa_dashboard (envío de notas)

> 🔵 **FE ACTUAL (28-oct-2026, SESIÓN NUEVA): T8.1 — Refinamiento del mensaje del
> docente (pedidos del usuario sobre la preview):** (1) en modo edición los dos
> campos (título y texto) **no queden tan juntos** (mayor separación) y el
> **título se alinee con el cuerpo** (mismo ancho/margen); (2) **opción "Eliminar
> mensaje"** para decidir NO enviar el mensaje del docente sin dejarlo "ahí"
> (botón dedicado en la cabecera de la preview, no una X dentro del campo).
> **Código local HECHO** (v2026102803): JS + CSS + lang ×3 + `$gmkeys` + AMD min
> regenerado; ver §T8.1.
>
> ✅ **CERRADA (deploy v2026102802 verificado 28-oct): T8 — Mensaje del docente:
> caja opcional RETIRADA + TÍTULO editable + "SOPORTE CURSOS VIRTUALES" (sin "DE").**
>
> **PETICIONES DEL USUARIO (texto literal):**
> 1. Quitar la caja "Mensaje para incluir en el correo (opcional)" porque más abajo
>    ya existe el campo Editar → el mensaje vive SOLO en la vista previa, QUEDE
>    **DEBAJO DE LA TABLA DE NOTAS** (donde realmente lo llega a ver el estudiante).
> 2. El texto "Un mensaje de tu docente" también debe quedar **editable**, y el
>    **predeterminado = "Mensaje de tu docente:"** (sin artículación + dos puntos).
> 3. El correo recibido dice "SOPORTE DE CURSOS VIRTUALES" → debe decir
>    **"SOPORTE CURSOS VIRTUALES"** (quitar el "DE").
>
> **🟢 ESTADO 28-oct-2026: TODO DEPLOYADO Y VERIFICADO; queda SOLO la aceptación
> del usuario en navegador (+ push `main`).**
>
> **HECHO (código terminado):**
> - `amd/src/enviarnotas.js`: textarea `#mwaGmComment` ELIMINADO (buildUI, bindEls,
>   listeners); estado module-level `commentValue` + `commentLabelValue`;
>   `previewLabelField()` nueva; `injectCommentAnchor()` crea
>   `strong[data-gm-field="commentlabel"]`; `syncCommentFromEditor()` sincroniza
>   título+texto (vacio del título = volver al default); `toggleCommentEdit()` hace
>   contenteditable AMBOS; `paintPreview()` pinta valores actuales; WS preview y send
>   mandan `comment` + `commentlabel`; hint `t('comment_hint')` reemplaza la caja.
> - `amd/build/enviarnotas.min.js`: REGENERADO con terser 5.51.2
>   (`npx --yes terser amd/src/enviarnotas.js -o amd/build/enviarnotas.min.js --compress --mangle`,
>   vía `cmd /c` para evitar el execution-policy de `npx.ps1`).
>   VERIFICADO: `data-gm-field="commentlabel"` ×2, `commentlabel` ×8,
>   `mwaGmComment` = 0, `comment_hint` ×1, `mwaGmPreviewEdit` ×2 (size 24.471).
> - `enviarnotas.php`: `$gmkeys` — fuera `comment`/`commentplaceholder`, entra `comment_hint`.
> - `lang/es|en|pt_br`: `gradesmail_email_commentlabel` = 'Mensaje de tu docente:'
>   (en: 'A message from your teacher:'; pt: 'Mensagem do seu professor(a):');
>   nueva clave `gradesmail_comment_hint` (3 idiomas); `comment`/`commentplaceholder`
>   conservadas como referencia (ya no las usa el JS).
> - `classes/external.php`: `build_grades_mail(..., $comment = '', $commentlabel = '')`
>   (cap 200 chars; vacio = default lang); el `<strong>` del título ahora lleva
>   `data-gm-field="commentlabel"`; WS `get_grades_mail_preview` (courseid, userid,
>   groupid, comment, **commentlabel**) y `send_grades_mail` (courseid, userids,
>   comment, groupid, **commentlabel**) — ORDEN de params == ORDEN de signature
>   (lección T2: call_user_func_array posicional). Lint `php -l` OK en los 7 ficheros.
> - `tests/send_grades_mail_test.php`: NUEVO `test_comment_label_editable_default_and_custom`
>   (default con anchor commentlabel; etiqueta custom en preview Y en envío — WYSIWYG).
> - `version.php`: **2026102801 → 2026102802** (sin schema; upgrade solo refresca
>   la fila `config_plugins.version`, como el T7.1).
> - `pix/banner.png`: OPTIMIZADO 730.767 → **203.507 bytes** (mismas dimensiones
>   1471×285; carga más ligera del `<img>` publico en el correo).
> - `relay smtp-unicla` (Vía B, 28-oct `_depl70_relay.sh`): **From con nombre
>   visible** — nueva constante `SMTP_FALLBACK_FROM_NAME` (default
>   **"SOPORTE CURSOS VIRTUALES"**, overridable por env; vacía = solo dirección)
>   y `msg["From"] = "NOMBRE <usuario>"`. Backup `/app/app.py.bak.20261028`
>   (MD5 `93cb39…`); live nuevo MD5 **`9b7a037eec35e2f3545d3a61e687df2b`**.
>   SMOKE real OK (Graph sigue 404/403 → **250 via SMTP FALLBACK**); correo de
>   prueba `[MWA] T8 prueba - remitente SIN 'DE' (borren)` enviado a
>   `desarrollador.mediosymediaciones@` (M365 respeta el From-name si coincide el
>   usuario autenticado → **pendiente: ojo del usuario en la bandeja**).
>   Copia del repo `_operacion/relay_smtp_unicla/app.py` + README sincronizados.
>
> **EVIDENCIA T8 (28-oct, `_diag66.sh`):** server live T7.1 (v2026102801 disco+DB,
> WS=20); **`noreplyname=false` (vacio)** en Moodle → el "SOPORTE DE CURSOS
> VIRTUALES" que ve el destinatario NO viene de Moodle ni del plugin (grep limpio)
> ni del código del relay (solo enviaba la dirección) → viene del **displayName del
> mailbox M365** (el relay Graph sigue **403 Authorization_RequestDenied** en
> `GET /users/…` → no se puede leer/parchear vía API; pendiente Vía A del admin,
> como en §T4).
>
> **🟢 DEPLOY v2026102802 COMPLETADO 28-oct (🟢 VERIFICADO):** staging local
> `_stage2802_block_mwa_dashboard` (109 archivos) → host `/tmp/_stage2802…` →
> `_depl71.sh` + `_inner10.sh`: PRE_OK → BACKUP_OK (`mwa_dashboard.bak.2026102802`
> conserva v2026102801) → SWAP_OK → **`++ 2026102802: Éxito`** → DB
> **`config_plugins version=2026102802 | WS=20`** → `docker restart moodle_app`
> **Up (healthy)** → HTTP root **200**, course 2 y 4 **200/303** → markers LIVE:
> `commentlabel` + `comment_hint` en min, **`mwaGmComment` = 0** (¡el textarea
> eliminado!), regresión `mwaGmPreviewEdit`/`act_hidden_tag` OK, `commentlabel`
> en external.php/enviarnotas.php, `gradesmail_comment_hint` ×3 lang, banner live
> 203 KB.
>
> **ESTADO AL CIERRE DE LA SESIÓN 28-oct (todo verificado; queda aceptación):**
> 1. **SOPORTE CURSOS VIRTUALES — RESUELTO DESDE NUESTRO LADO:** origen
>    confirmado (vía `_diag66.sh`): Moodle `noreplyname=false` (vacío), relay sin
>    nombre en el From, plugin limpio → era el **displayName del mailbox M365**.
>    Patch aplicado 28-oct (`_depl70_relay.sh`): la Vía B del relay ahora firma
>    el From con `SOPORTE CURSOS VIRTUALES <soporte.cursosvirtuales@…>` (constante
>    `SMTP_FALLBACK_FROM_NAME`). SMOKE: 250 via SMTP FALLBACK → **el usuario debe
>    ver el correo `[MWA] T8 prueba - remitente SIN 'DE' (borren)` y confirmar el
>    nombre en la bandeja**. ⚠️ Si M365 siguiera mostrándole "DE" (rewriting por
>    displayName del mailbox), la única vía = admin M365 cambia el displayName de
>    `soporte.cursosvirtuales@` (mensaje listo para entregarle) o crea un mailbox
>    con el nombre correcto. (El parche Graph sigue en Vía A §T4.)
> 2. **DEPLOY v2026102802 — ✅ HECHO 28-oct** (evidencia arriba): `_depl71.sh` +
>    `_inner10.sh`, PRE/BACKUP/SWAP/`++ Éxito`/DB 2026102802|WS=20/healthy/200-303/
>    markers T8 + regresión OK. Backup vivo: `mwa_dashboard.bak.2026102802`.
> 3. **🔵 PENDIENTE ÚNICA — ACEPTACIÓN USUARIO** (navegador, curso de prueba):
>    (a) la caja "Mensaje para incluir en el correo (opcional)" **ya no existe**;
>    (b) en la vista previa, **DEBAJO de la tabla de notas**, el título
>    "Mensaje de tu docente:" y el cuerpo son editables con el botón "Editar"
>    (título vacío en la edición = vuelve al default);
>    (c) 1 envío real (canal Vía B OK) con el mensaje abajo de la tabla, remitente
>    "SOPORTE CURSOS VIRTUALES" (sin "DE") y auditoría `status=ok`.
> 4. **PUSH `main` — ✅ HECHO 28-oct:** commit **`864bf8d`** (13 archivos: código T8
>    + banner optimizado + relay T8 + plan/diario) → `origin/main =
>    864bf8db3c8f9e4f22faf1d113e669563f2a6498` (verificado `ls-remote`).
>
> **ACCESSOS (sesión de trabajo, no versionar):** server 192.168.25.4
> (user `proyectosmym`, ssh OK); Moodle **5.0.1**; host contenedor `moodle_app`
> (ruta live `/bitnami/moodle/blocks/mwa_dashboard`, php
> `/opt/bitnami/php/bin/php`); relay SMTP contenedor `smtp-unicla`
> (Graph credentials + fallback SMTP AUTH, ver `_operacion/relay_smtp_unicla/`).

> 🔵 **FE ACTUAL (28-oct-2026): T7.1 — Banner a ANCHO COMPLETO (responsivo).**
> El usuario vio la preview (captura, 28-oct): el banner queda **más pequeño que la
> tabla de notas**. Causa: el `<img>` lleva `max-width:600px` (decisión T7 para el
> motor Word/Outlook) pero la tabla es `width=100%` → en preview/clientes web el
> banner mide 600px y la tabla todo el ancho. **Decisión T7.1:** quitar `max-width:600px`
> (queda `width:100%;height:auto`, mismo ancho que la tabla en TODOS los renderizados
> web + preview) y **conservar el atributo `width="600"`** solo como fallback del motor
> Word de Outlook desktop (ignora %: sin el attr renderizaría el original 1471px).
> **✅ DEPLOY VERIFICADO 28-oct (v2026102801)** sobre T7: `upgrade ++ Éxito`, DB
> `version=2026102801 | WS=20`, markers live (banner **sin `max-width:600px`**),
> banner público 200 y **PROBE ALL PASS 6/6** (banner sobre saludo,
> `width:100%;height:auto`, sin warnings PHP) + hotfix guard `$SESSION->userid`
> (sha256 live=local `f7f8a02b…`). Commit A (`9a2b174`) = estado T7 (referencia del
> deploy anterior). **PENDIENTE: aceptación del usuario (preview + envío real) y
> push `main` (commit B).** Ver §T7.1.
>
> ✅ **ESTADO 27-oct-2026 (DESPLEGUE VERIFICADO):** despliegue N.º2
> (`_depl27` + `_inner5`) **COMPLETADO y verificado en vivo** (verificación del
> 27-oct, runner `_diag31.sh`):
> - disco: `version.php = 2026102601`, markers `mwaGmPreviewEdit` / `chip-student` /
>   `histClear` presentes en `amd/build/enviarnotas.min.js`, anclas
>   `data-gm-editable` ×2 en `classes/external.php`, 2 WS nuevos en `db/services.php`,
>   **0 directorios `stage*` anidados**.
> - backup vivo: `mwa_dashboard.bak.20261027` (con v2026102301) conservado.
> - DB: `config_plugins block_mwa_dashboard.version = 2026102601`; **20 WS**
>   `block_mwa_dashboard_*`, incluidos `delete_grades_mail_record` y
>   `clear_grades_mail_history` (REGISTERED).
> - HTTP: sitio `200`; `enviarnotas.php?course=2` → `303` a login ✔.
> ✅ TODO CERRADO 27-oct-2026: verificación funcional OK en el sitio (usuario: T1 Editar
> preview, T2 envío real Gmail/Outlook con tabla bordes/cabecera/fondo/nota coloreada,
> T3a chips sin re-query, T3b borrar fila + limpiar historial) y push a GitHub (2 commits
> en `main`, rama en sincronía con `origin/main`).
> Diario de la sesión: **`DIARIO-DESARROLLO-2026-10-27.md`** (raíz del workspace).
> Antecedente: **`DIARIO-DESARROLLO-2026-10-26.md`** (contexto del 26-oct).
>
> ✅ **FASE ANTERIOR (27-oct tarde): T4 — Canal de email M365** — **CANAL REPARADO vía B
> (fallback SMTP AUTH en el relay, 250 OK verificado en log `_diag52`)**; pendiente
> SOLO la confirmación final del usuario (email de prueba en bandeja + 1 envío desde la
> UI). Vea §T4 abajo (Vía A de raíz sigue pendiente con el admin M365, ya no bloquea).
>
> ✅ **FASE ANTERIOR (27-oct): T5 — Curso 4 sin actividad evaluada / sin nota máxima —
> CERRADA.** Diagnóstico (NO es bug: los 4 ítems `mod=quiz` del curso 4 están `hidden=1`
> en la libreta). Mejora UI v2026102701 (hint `evalselect_empty`, 3 lang + JS + build)
> **desplegada y 100% VERIFICADA en vivo** (`_diag56.sh`, 2ª sesión 27-oct: version 2026102701,
> WS=20, todos los markers, 3 langs, HTTP 200/303, restart OK). Ver §T5 y diario §7 + §8.
>
> ✅ **FASE CERRADA (27-oct): T6 — "Enviar notas" con ítems ocultos en la libreta
> (curso 4).** DECISIÓN USUARIO: cambiar el plugin → ítems `hidden=1`
> SELECCIONABLES en "Enviar notas" (marcados "(oculta)") y el docente los ve /
> previsualiza / envía SIN desocultar la libreta. Código + deploy v2026102702
> verificados en vivo (§T6); pendiente SOLO la aceptación del usuario en
> navegador (curso 4) + push a `main`.
>
> 🔵 **FASE ACTUAL (27-oct, sesión 4): T7 — Banner institucional en el correo de
> notas.** El usuario pasó 2 capturas (sin banner vs cómo quiere llegar) y el
> archivo `Banner/Banner.png` (1471×285, 730 KB). Diseño + estado en §T7.
> **Evidencia (sesión 4):** el usuario adjuntó el correo REAL recibido en su bandeja
> (Gmail/Outlook) → **T4 queda de hecho aceptada** (canal vía B entrega en bandeja;
> remitente `soporte.cursosvirtuales@`; tabla bordes/nota coloreada).
> **Flujo de trabajo recordado por el usuario:** la verdad de origen está en el server
> (Moodle **5.0.1** en 192.168.25.4, contenedor `moodle_app`); lo local
> (`block_mwa_dashboard/`) se mantiene al día para subir al repo
> `Christian-Pemberty/Dashboard-Moodle` (rama `main`).

> Estado actual: v **2026102701** verificada en vivo; **T6 CÓDIGO + DEPLOY v2026102702
> COMPLETADOS** (27-oct, sesión 3): `api::get_grades(includehidden)` + filtro + meta
> `act_hidden_i` · WS `get_grades` con `includehidden` · `get_grades_send_options` con
> `hidden` (sin filtro en la query de `grade_items`) · `build_grades_mail` con hidden
> incluido · JS (`loadInitial` + etiqueta `(oculta)`) · lang en/es/pt_br (`act_hidden_tag`,
> `evalselect_empty` reescrita) · `$gmkeys` · tests (1 invertido + 2 nuevos) · AMD
> regenerado (terser 5.51.2, 12/12 markers). Deploy verificado: version=2026102702,
> WS=20, markers LIVE, restart healthy, HTTP 200/303. **PENDIENTE: aceptación del usuario
> en el navegador (curso 4: selector con quizzes "(oculta)", nota mínima activa, preview
> con notas, 1 envío real) + push a `main`.**
> Base de trabajo: `block_mwa_dashboard/` (raíz = plugin), tests en `tests/send_grades_mail_test.php`.
> Contrato WYSIWYG existente: preview y envío usan la misma fuente de verdad —
> `\block_mwa_dashboard\external::build_grades_mail()` (external.php:815) —
> así lo que se previsualiza es exactamente lo que se envía.

**Orden de ejecución: T1 → T2 → T3** (T2 solo toca `build_grades_mail()`; T3 no depende de nada).
Al terminar: bump de versión → `upgrade.php` → deploy → push a GitHub (procedimiento de la sesión 23-oct).

---

## T1 — Editor de Vista Previa con Campos Protegidos

**Objetivo:** botón **"Editar"** en la tarjeta de previsualización. Solo la sección
*"A message from your teacher / Un mensaje de tu docente"* (el comentario) es editable,
en vivo, sobre la propia vista previa. El resto es de solo lectura, **por diseño y por backend**.

### 1.1 Backend — zona editable identificable en el HTML del correo
Archivo: `classes/external.php` → `build_grades_mail()` (línea 815).

- Envolver la sección de comentario (hoy líneas 887–892) en una etiqueta ancla estable:
  ```php
  $bodyhtml .= \html_writer::start_tag('div', ['data-gm-editable' => 'comment']);
  $bodyhtml .= \html_writer::tag('p', \html_writer::tag('strong',
      s(get_string('gradesmail_email_commentlabel', 'block_mwa_dashboard'))));
  $bodyhtml .= \html_writer::tag('p', format_text($comment, FORMAT_MOODLE,
      ['context' => \context_course::instance($courseid)]), ['data-gm-field' => 'commenttext']);
  $bodyhtml .= \html_writer::end_tag('div');
  ```
- El resto del cuerpo (saludo, fecha, nota global, tabla ACT/NOTA, pie institucional)
  queda **sin** `data-gm-editable`: el frontend solo habilita edición dentro del ancla.

> Nota: `build_grades_mail()` sin comentario NO emite la sección; si el usuario pulsa
> "Editar" sobre una previsualización sin comentario, el frontend inyecta el ancla vacía
> (mismo marcado que arriba) y escribe su texto ahí. Al guardar, `comment` viaja como siempre.

### 1.2 Frontend — botón Editar + edición en vivo
Archivo: `amd/src/enviarnotas.js`.

- UI (`buildUI()`, tarjeta de preview, hoy líneas 283–291):
  - En `mwa-gm-cardhead` (junto al `<h2>` y `#mwaGmPreviewStudent`) agregar:
    `<button type="button" class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmPreviewEdit">…Editar…</button>`
- Comportamiento (`doPreview()` línea 561 / nueva `toggleCommentEdit()`):
  1. Tras cargar el preview (`els.previewBody.innerHTML = res.html`), localizar el ancla:
     `els.previewBody.querySelector('[data-gm-editable="comment"]')` (o la inyectan si no existe).
  2. Al pulsar **Editar**: el párrafo `[data-gm-field="commenttext"]` pasa a
     `contenteditable="true"` + clase `mwa-gm-editing` (bordes visuales, CSS en `styles/gradesmail.css`);
     el botón cambia a "Listo".
  3. `input` sobre la zona editable → **sincronía bidireccional en vivo**:
     - zona editable → `els.comment.value` (textarea oculta visible, fuente de verdad para el POST)
     - cambio manual en la textarea (si sigue visible) → zona editable del preview.
  4. Al pulsar "Listo": `contenteditable="false"`; el texto queda en `els.comment`.
  5. Al cambiar de estudiante (T3) o al re-previsualizar: re-renderizar el preview y
     **volver a inyectar** el ancla con el comentario actual (nunca conservar HTML del usuario).
- **Camios protegidos (solo lectura, sin UI de edición ni camino de POST):**
  nombre del alumno, fecha/hora, tabla de notas, pie institucional. La única variable del POST
  sigue siendo `comment`; el servidor nunca recibe `subject`/`html`/`name`.

### 1.3 Aseguramiento backend (verificación explícita, no solo "confiar")
- `send_grades_mail()` (external.php:1005) ya es seguro: parámetros validados
  `(courseid, userids, comment, groupid)`; el cuerpo se reconstruye con
  `build_grades_mail()` desde `api::get_grades()` (DB) y el comentario es el único input libre.
  El core de external API rechaza parámetros no declarados → ningún campo inventado llega.
- **Ajuste a añadir:** en `build_grades_mail()`, el comentario pasa hoy por
  `format_text(..., FORMAT_MOODLE)` que acepta HTML. Mantener, pero:
  - limitar longitud (p. ej. 2000 caracteres, `trim` + recorte con aviso silencioso),
  - `s()` ya aplica a todas las etiquetas estáticas (no tocar).
- **Test anti-manipulación** (en `tests/send_grades_mail_test.php`):
  llamar `send_grades_mail()` con `comment` = HTML malicioso/foráneo y afirmar que
  el `subject`, el saludo y la tabla de notas del HTML resultante provienen de la DB
  del estudiante y NO del `comment` (el comentario sólo aparece dentro del ancla `<div data-gm-editable>`).

### 1.4 Nuevas cadenas (en/es/pt_br) + `enviarnotas.php`
- `gradesmail_preview_edit` ("Editar" / "Edit" / "Editar"),
  `gradesmail_preview_done` ("Listo" / "Done" / "Concluído") — ambas a `$gmkeys` de `enviarnotas.php` (lista, línea 43).

### Aceptación T1 — ✅ VERIFICADO EN VIVO (user confirm, 27-oct-2026)
- [x] Botón Editar aparece solo en la tarjeta de preview; alterna Editar ↔ Listo.
- [x] Se edita únicamente el párrafo de comentario; nombre/fecha/tabla/pie no son editables ni reciben el texto.
- [x] El texto editado llega en el POST como `comment` y el correo recibido lo contiene dentro de la sección "mensaje del docente".
- [x] Test anti-manipulación verde; lint verde; preview ↔ envío WYSIWYG intacto.

---

## T2 — Correos HTML con estilos inline (Gmail/Outlook-safe)

**Objetivo:** el correo llega **HTML** con tabla y colores estables en Gmail/Outlook.
Hoy `email_to_user($recipient, $sender, $subject, $plain, $html)` (external.php:1088)
ya envía la parte HTML (multipart/alternative) — el problema es que el HTML actual es
mínimo: `<table width=100% cellspacing=0 cellpadding=4>` sin bordes ni colores
(líneas 872–885 → así se ve "a pelo" en los clientes).

**Regla de oro email-safe (Gmail/Outlook web):**
sin `<style>` en head, sin floats, sin CSS externo, `table` con `max-width:600px` +
estilos **inline** en cada etiqueta, font-stack web-safe.

### Cambios — `build_grades_mail()` (external.php:815)
Reconstruir `$bodyhtml` manteniendo **las mismas cadenas** lang y el mismo ancla de T1:

| Elemento | Estilo inline objetivo (ejemplo) |
|---|---|
| envoltorio | `<div style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937;">` |
| saludo / intro | `<p style="margin:0 0 12px;">…</p>` |
| nota global | `<p style="margin:0 0 16px;"><strong>…</strong></p>` |
| tabla | `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 0 16px;border:1px solid #d9dee5;">` |
| `th` (ACT/NOTA) | `style="background:#f1f3f7;color:#1f2937;font-size:12px;letter-spacing:.4px;text-transform:uppercase;padding:8px 12px;text-align:left|right;border-bottom:1px solid #d9dee5;"` |
| `td` actividad | `style="padding:8px 12px;border-bottom:1px solid #e6eaef;text-align:left;color:#1f2937;"` |
| `td` nota pasada | idem + `color:#1e7e34;font-weight:bold;` |
| `td` nota pendiente | idem + `color:#b26a00;font-style:italic;` |
| ancla comentario | `<div data-gm-editable="comment" style="margin:0 0 16px;">` (párrafos según T1) |
| pie | `<p style="margin:16px 0 0;padding-top:12px;border-top:1px solid #e6eaef;color:#6b7280;font-size:12px;">` |

- Colores por estado: `grades_mail_format_value()` (línea 793) + `get_grades_mail_preview()`
  ya devuelven `html`; añadir clasificación pasada/pendiente usando el mismo criterio
  que el frontend (`pending` = "-"), para aplicar el `td` coloreado. (Sin cambiar el plain.)
- **Plain:** `html_to_text()` (línea 900) sigue derivando del mismo `$bodyhtml`
  → el texto plano no regresa a ser lo dominante: Gmail/Outlook muestran `text/html`.
- **Vista previa en app:** como consume el mismo `html`, la preview queda WYSIWYG real
  (la tabla pasará a verse igual que en el correo). Revisar `.mwa-gm-mail` en
  `styles/gradesmail.css` para no forzar paddings que distorsionen.

### Aceptación T2 — ✅ VERIFICADO EN VIVO (user confirm, 27-oct-2026: envío real Gmail/Outlook web)
- [x] Correo real en Gmail y Outlook web: tabla con bordes, cabecera con fondo, nota colorea,
      pie separado — sin bloques de texto en blanco.
- [x] `email_to_user` mantiene `(plain, html)` en ese orden (verificar llamada 1088 en el diff).
- [x] Preview in-app == cuerpo del correo (WYSIWYG). Tests de preview siguen verdes (+1 test
      que aserte la presencia de `border:` inline en el HTML de la tabla).

---

## T3 — Chips de seleccionados en Vista Previa + Limpieza de Historial

### 3a. Selector de estudiantes (chips) en la vista previa
Archivo: `amd/src/enviarnotas.js`.

- Estado nuevo: `previewSel = { chips: [userid...], active: null, cache: Map<userid, resp> }`.
- Al marcar/desmarcar checkboxes (`updateCount()`, evento `change` en `els.rows`):
  reflejar en la tarjeta de preview un **rastro de chips** por estudiante seleccionado
  (orden de selección): `chipHtml()` ya existe (filtros) — reutilizar el patrón/estilo
  con clase `mwa-gm-chip-student` + `data-studentid`.
- `doPreview()` (hoy: `userid: ids[0]`, línea 574) → cambiar a
  `showStudentPreview(userid)`:
  1. activar chip (clase `is-active`);
  2. si no está en `cache`: `get_grades_mail_preview({courseid, userid, groupid, comment})`;
  3. pintar nombre, asunto y HTML (con ancla de comentario según T1 y el valor de `comment` vigente);
  4. guardar en `cache` → alternar estudiantes sin rellamada.
- Desmarcar un estudiante → quitar chip; si era el activo, activar el anterior (o limpiar la preview si no quedan).
- Enviar sigue usando `selectedIds()` completos (sin cambios en el POST).
- Cadenas nuevas: `gradesmail_preview_chips_hint` (p. ej. "Haz clic en un nombre para ver su correo") en en/es/pt_br + `$gmkeys`.

### 3b. Gestión del Historial (borrado por fila + limpiar todo)
**Backend** — `classes/external.php` (sección `-- grades send history --`, línea 1295),
imitando el patrón existente `delete_intervention()` (línea 1601):

1. `block_mwa_dashboard_delete_grades_mail_record(int $id)`:
   - `validate_parameters` `(id: PARAM_INT)`.
   - `get_record('block_mwa_dashboard_messages', [id => ...], '*', IGNORE_MISSING)`;
     no existe → `['success' => false]`.
   - **Restricción dura:** `$record->intervention_reason !== 'Grades'` → rechazar
     (no borrar intervenciones pedagógicas por esta vía) → `['success' => false]`.
   - `require_capability('block/mwa_dashboard:view')` + `manageinterventions` en el contexto del curso.
   - `teacherid == $USER->id` O `moodle/site:config` (mismo criterio que `delete_intervention`).
   - `$DB->delete_records(...)` en transacción delegada → `['success' => true]`.
2. `block_mwa_dashboard_clear_grades_mail_history(int $courseid, int $groupid = 0)`:
   - mismas capabilities + `validate_group_scope()`.
   - `DELETE` de `block_mwa_dashboard_messages` con `courseid + intervention_reason='Grades'`
     (+ teacherid propio salvo admin — **decisión**: si multi-docente, borrar solo del docente
     actual + admin; confirmar con el usuario si se prefiere "todo el curso").
   - devuelve `['success' => true, 'deleted' => int]`.
3. `db/services.php`: registrar los 2 servicios (`type => 'write'`, `ajax => true`,
   `loginrequired => true`) — los nuevos entran a la DB en el `upgrade.php` del deploy.

**Frontend** — `amd/src/enviarnotas.js` (`renderHistory()` línea 709, `buildUI()` líneas 307–333):

- Cabecera de la tabla de historial: columna extra `''` (o botón flotante por fila al final de la fila):
  `<button class="mwa-gm-iconbtn" data-delhist="{id}" title="{delete}">✕</button>`.
- Delegación de click en `els.histRows`: botón por fila → `openConfirm(t('hist_del_title'), t('hist_del_body'), …)`
  → `callWS('block_mwa_dashboard_delete_grades_mail_record', {id})` →
  quitar la fila de `hist.rows`/`hist.total` y `renderHistory()`.
- Botón global junto al `select` de estado (línea 311):
  `<button class="mwa-gm-btn mwa-gm-btn-sec" id="mwaGmHistClear">…Limpiar historial…</button>`
  con icono de basura; deshabilitado si `hist.total === 0`.
  Confirmación reforzada (`hist_clear_title` / `hist_clear_body` que avisa que es **permanente**).
- Cadenas (en/es/pt_br) + `$gmkeys` de `enviarnotas.php`:
  `hist_del_title`, `hist_del_body`, `hist_del_row`, `hist_clear`, `hist_clear_title`, `hist_clear_body`, `hist_clear_done`.

### Aceptación T3 — ✅ VERIFICADO EN VIVO (user confirm, 27-oct-2026)
- [x] Seleccionar 3 estudiantes → 3 chips con su nombre; clic para alternar preview sin re-query
      (verificar con devtools/Network que una llamada por estudiante máximo).
- [x] Borrar 1 fila del historial → desaparece de la tabla Y de la BD (reabrir Historial → no está).
- [x] "Limpiar historial" → confirmación → tabla vacía + `hist_empty` visible + 0 filas en BD
      (consulta directa) y las intervenciones pedagógicas de `block_mwa_dashboard` intactas.
- [x] Test PHPUnit nuevo: `delete_grades_mail_record` (propio/ajenos/no-Grades/admin) y
      `clear_grades_mail_history` (conteo borrado, capabilities) — mismo estilo
      `tests/send_grades_mail_test.php` (advanced_testcase, `enrol_user_in_course()`).

---

## Cross-cutting (fin de sesión)

1. **Versión:** `$plugin->version = 2026102301` → **`2026102302`** (`version.php`).
   Necesario para que `upgrade.php` registre los WS nuevos de T3b.
2. **AMD:** regenerar `amd/build/enviarnotas.min.js` a partir de `amd/src/enviarnotas.js`
   (T1+T3a). *Lección de la sesión 22-oct:* min desfasado = UI vieja. Verificar que el min
   contiene `wmaGmPreviewEdit` / `histClear` / `data-gm-editable` antes del deploy.
3. **Tests:** `php unit Tests` de `block_mwa_dashboard` verde (enviarnotas + los nuevos).
4. **Deploys (procedimiento 23-oct, verificado):**
   1. `scp` zip a `/tmp` → `docker cp` al contenedor `moodle_app`.
   2. Extraer con `php ZipArchive` a `/bitnami/moodle/blocks/block_mwa_dashboard` y
      `mv` dentro de `blocks/` a **`mwa_dashboard`** (carpeta correcta).
   3. `php admin/cli/purge_caches.php`; `docker restart moodle_app`; probar `curl` host.
   4. **`php admin/cli/upgrade.php --non-interactive`** para los 2 WS nuevos (T3b).
   5. Verificar `MWA_GRADEMAIL_STR` ≥ 2 en `enviarnotas.php` y `data-gm-editable` presente.
5. **GitHub:** clonar `github:Christian-Pemberty/Dashboard-Moodle`, reemplazar raíz por el
   plugin (como se hizo en 23-oct), commit + push a `main`.

## Cierre 27-oct-2026 — ejecución y verificación

1. **Versión:** la subida efectuada fue **`2026102601`** (no `2026102302` como decía §Cross-cutting;
   más alto y correcto). `upgrade.php --non-interactive` corrió, **bump OK** y los 2 WS de T3b
   quedaron registrados (verificado en `mdl_external_functions`: 20 WS totales).
2. **Markdown/AMD:** min regenerado con los 3 markers T1/T2/T3 (verificado en live).
3. **Tests:** 13 tests PHPUnit en `tests/send_grades_mail_test.php` (4 nuevos: anti-manipulación,
   estilos inline, delete owner-only/refusa no-Grades, clear scoped/admin).
4. **Docs:** `README.md` (sección 4.3.1), `ESTRUCTURA.md` (WS +6, §15 módulo "Enviar notas" — 2026-10-27),
   y este plan actualizado.
5. **GitHub:** commit de código ya local (`105c5d5`); commit de plan+docs y push a `main` (27-oct).

### Pendiente (único) — ✅ RESUELTO 27-oct-2026
- [x] **Verificación funcional en el sitio con cuenta docente** ( usuario, 27-oct):
      T1 Editar en preview ✔ · T3a chips por estudiante ✔ · T3b borrar fila + limpiar
      historial ✔ · envío real (Gmail/Outlook web) T2: tabla con bordes, cabecera con
      fondo, nota coloreada ✔. Criterios: aceptación T1/T2/T3 marcadas arriba.

## T4 — Canal de email M365: diagnóstico y remediación (ABIERTA, 27-oct tarde)

> El plugin quedó completo y verificado (T1–T3). Sin embargo el **correo real no llega**
> a los destinatarios. Diagnóstico completo (no es bug del plugin): falla el canal
> servidor → Microsoft 365.

### 4.1 Evidencias (verificado en vivo, `_diag42`–`_diag45`, 27-oct)

1. **Moodle → relay `smtp-unicla`: OK** (SMTP handshake, DATA 250).
2. **Relay → Microsoft Graph: RECHAZADO en TODOS los senders** (probe de hoy):
   - `soporte.mym@uniclaretiana.edu.co` → `sendMail` **404 `MailboxNotEnabledForRESTAPI`**
     → esa buzonería está inactiva / soft-delete / on-prem **hoy** (antes sí enviaba).
   - `soporte.cursosvirtuales@...` → **403 `ErrorAccessDenied`**.
   - `desarrollador.mediosymediaciones@...` → **403 `ErrorAccessDenied`**.
   - `GET /users/{sender}` → **403 `Authorization_RequestDenied`** en los 3 → la app
     **tampoco tiene** `User.Read`. El token AAD se obtiene bien (secret OK, claims de la
     app "Dspace" sin scope = permisos de app, no de usuario).
3. **Línea de tiempo (log del relay, con timestamps):**
   - **Último envío EXITOSO: 2026-10-26 14:04 UTC** (remitente `soporte.cursosvirtuales@`
     — ya entonces `soporte.mym` estaba fallando → la buzonería cayó ~26-oct).
   - **Primer FALLO: 2026-10-27 13:18 UTC** (= coincida con la queja del usuario:
     auditoría `status=error` 27-oct 08:18:50 hora local -5 = 13:18 UTC ✓).
   - Conclusión: **entre el 26-oct y el 27-oct cambió algo en M365/Azure AD** al lado de
     la institución (revocación de consent de la app "Dspace" o eliminación de la app
     registration / cambio de política). Nada en nuestro código cambió.
4. **Auditoría BD** (`mdl_block_mwa_dashboard_messages`): filas `status=error` de la queja
   (el mensaje Moodle sí salió al relay; el envío final falló).

### 4.2 CAUSA RAÍZ (lado Microsoft 365, fuera del plugin y del server)

Dos defectos independientes, ambos en el tenant M365 de la institución:

1. **App registration "Dspace"** (`CLIENT_ID=9f47a629-…`, tenant `498edaab-…`):
   **perdió el consentimiento** de los permisos de aplicación `Mail.Send` (+ `User.Read`).
   → 403 `ErrorAccessDenied` en `sendMail` para TODOS los remitentes.
2. **Mailbox `soporte.mym@uniclaretiana.edu.co`**: deshabilitado / soft-deleted /
   migrado on-prem → 404 `MailboxNotEnabledForRESTAPI` (era el primer sender de la cadena).

### 4.3 Acciones pendientes

**Vía A — remediar de raíz (admin M365 / Azure AD, mensaje para entregar al admin):**

> "Se requiere en el tenant M365:
> (1) En Azure AD → Application registrations → **"Dspace"** (`client_id 9f47a629-be68-46e2-8e59-8f0b717cd79b`):
> verificar/crear permisos de aplicación **Mail.Send** y **User.Read** y conceder
> **Admin consent** (Consent to application... / Grant admin consent → Microsoft Graph);
> (2) En EXO: reactivar/re-crear el mailbox **soporte.mym@uniclaretiana.edu.co**
> (hoy responde `MailboxNotEnabledForRESTAPI`) o, en su defecto, indicar el mailbox
> correcto para remitente de soporte;
> (3) Confirmar que los mailboxes de la cadena de fallback
> (**soporte.cursosvirtuales@**, **desarrollador.mediosymediaciones@**) existen y no
> quedaron sin licencia EXO."

Tras aplicar: reprobe (script `_diag42` §D) y test de envío real desde `enviarnotas`.

**Vía B — fallback SMTP AUTH (si el admin tarda / no puede reconceder):**
Agregar al relay `smtp-unicla` (`/app/app.py`, 178 líneas) un **segundo backend** de envío:
- `smtplib` a `smtp.office365.com:587` (STARTTLS, `LOGIN/PLAIN` con cuenta M365 +
  **contraseña de app**) como último eslabón cuando Graph falle.
- **REQUIERE:** cuenta M365 con SMTP AUTH habilitado y su app password.
- **DECISIÓN 27-oct (usuario):** remitente Vía B = `soporte.cursosvirtuales@uniclaretiana.edu.co`.
- Riesgo: el tenant puede bloquear SMTP AUTH globalmente (política "Modern only") →
  probar ANTES de reimplementar. Verificado 27-oct: `smtp.office365.com:587` **respone**
  desde el relay (`BN1PR12CA0016.outlook.office365.com`, `STARTTLS` ok, EHLO 250).
- **ESTADO: IMPLEMENTADO EN EL RELAY (27-oct, `_depl47.sh`):** nuevo bloque
  `SMTP_FALLBACK_*` + `smtp_fallback_send()` al final de `handle_DATA()` (último
  eslabón, solo si hay credenciales — sin credenciales el comportamiento es idéntico
  al anterior, verificado por smoke). Credenciales por **env** (`SMTP_FALLBACK_USER`/
  `SMTP_FALLBACK_PASS`) o archivo `/app/.smtp_creds` (línea 1 usuario, línea 2 app
  password; `chmod 600`). Backup previo: `/app/app.py.bak.20261027`.
  **HECHO 27-oct:** app password cargada en `/app/.smtp_creds` (600, 2 líneas),
  `docker restart smtp-unicla`, **SMOKE REAL EXITOSO** (log `_diag52`): cadena Graph
  cae (404/403) → `Intentando SMTP AUTH fallback como soporte.cursosvirtuales@` →
  **`Correo enviado correctamente POR SMTP FALLBACK` → 250 OK**.
  **PENDIENTE FINAL:** usuario confirma (a) el email de prueba en su bandeja
  (`desarrollador.mediosymediaciones@`) y (b) 1 envío real desde la UI `enviarnotas`
  (verifica auditoría `status=ok` + entrega Gmail/Outlook).
- Nota: hoy el relay usa `saveToSentItems=True` vía Graph; con SMTP puro no hay
  "Sent Items" (aceptable para notificaciones a estudiantes).

### 4.4 Verificación al cerrar T4
- [x] Canal de envío vivo: **relay → Office 365** devuelve **250** vía fallback SMTP
      (27-oct, `_diag52`). Vía Graph aún 404/403 (pendiente Vía A, no bloquea ya).
- [ ] Usuario confirma: email de prueba `[MWA] Canal reparado - prueba V2 SMTP fallback`
      llega a `desarrollador.mediosymediaciones@` (remitente `soporte.cursosvirtuales@`).
- [ ] Envío real vía UI `enviarnotas` (cuenta docente): destinatario de prueba recibe el
      correo en < 1 min (Gmail/Outlook web), con tabla bordes/cabecera/nota coloreada (T2).
- [ ] Auditoría BD: nueva fila `status=ok` (no `error`), `moodle_msgid` presente.
- [x] Plan y diario actualizados; cambio en el relay con backup previo
      (`/app/app.py.bak.20261027`) **y respaldo permanente del relay en el repo**
      (`_operacion/relay_smtp_unicla/` — `app.py` Vía B + original + README de restore,
      commit `636739c`; el `CLIENT_SECRET` de Azure NO se versionó: **GitHub Push
      Protection lo bloqueó** y las copias del repo lo llevan como placeholder).

## T5 — Curso 4: sin "Actividad evaluada" ni nota máxima (ABIERTA, 27-oct tarde-noche)

> **Síntoma (usuario, 27-oct):** en `https://medicionra.uniclaretiana.edu.co/blocks/
> mwa_dashboard/enviarnotas.php?course=4` no aparece el selector **"Actividad
> evaluada"** ni el campo de **nota mínima** (en curso 2 sí funcionan).

### 5.1 Diagnóstico (evidencia 27-oct, runner `_diag54.sh`, PHP CLI en `moodle_app`)

| Dato | Curso 2 (OK) | Curso 4 (afectado) |
|---|---|---|
| Estudiantes (rol `student`) | 7 | 12 |
| `grade_items` mod / visibles | 1 / **1** | 4 / **0** |
| Ítems quiz | gi#2 `hidden=0` max=10 pass=6 ("Prueba funcional – Momento 1 RA") | gi#9/gi#10/gi#12 **`hidden=1`** (max 5/5/10, sin pass): Momento 1, Momento 2, Momento 3 — más gi#11 del quiz inst=7 cuyo cm#33 está **en borrado** (Momento 2 duplicado) |
| `excludedcmids` (ajuste del bloque, curso) | vacío | vacío |
| `api::get_grades()` | 8 filas, meta `activity_names` **SÍ** (acts=1) | 12 filas, meta **NO** → fallback `grade_roster_rows` |

**CAUSA RAÍZ: NO es bug del plugin.** Los 4 ítems de quiz del curso 4 están
marcados `hidden=1` en la libreta de calificaciones (= el docente no quiere
publicarlas todavía, comportamiento de Moodle: los ítems ocultos no se muestran
ni se envían a estudiantes). La cadena `api::get_grades()` filtra ítems ocultos
por diseño → quedan 0 → entra el **fallback de roster** previsto para este caso
(los 12 estudiantes SÍ aparecen; su total sale del ítem `itemtype='course'` max 25)
pero **sin** la fila meta `activity_names` → `get_grades_send_options` devuelve
`['activities' => []]` → el selector y el campo de nota mínima se muestran
**deshabilitados y en silencio** (faltaba explicación → mejorada en §5.3).

### 5.2 Acción operativa (docente, inmediata, no requiere código)
En **curso 4 → Calificaciones**: desocultar los ítems de los quizzes (ícono de ojo
tachado → mostrar; o "Opciones de visualización" por actividad). Con `hidden=0`:
- el selector de actividad + campo de nota mínima se habilitan **exactamente como
  en curso 2**;
- opcional: definir "Nota de aprobación" del ítem para tener el umbral exacto
  (si no la hay, el plugin usa por defecto el 50% de la máxima, tal como lo indica
  el hint `passmin_fallback`).

### 5.3 Mejora UX implementada (código, v2026102701 — 27-oct)
- Cadena nueva **`gradesmail_evalselect_empty`** (en/es/pt_br) + añadida a `$gmkeys`
  de `enviarnotas.php`.
- `amd/src/enviarnotas.js` → `refreshPassmarkHint()`: si no hay actividad
  seleccionable y el curso SÍ tiene estudiantes → el hint muestra la causa
  (actividades ocultas en la libreta) y cómo remediarla. El fallback de roster
  **se mantiene** (ítems ocultos no se publican/envían: la decisión de la libreta
  manda).
- `amd/build/enviarnotas.min.js` **regenerado** con terser 5.51.2 (`--comments
  jsdoc`): `node --check` OK; markers presentes: `mwaGmPreviewEdit`, `histClear`,
  `data-gm-editable`, `chip-student`, `mwaGmPreviewChips`, `MWA_GRADEMAIL_STR`,
  `delete_grades_mail_record`, `clear_grades_mail_history`, `evalselect_empty`.
  Backup previo: `enviarnotas.min.js.bak.20261027` (raíz del workspace).
- `version.php`: 2026102601 → **2026102701** (bump de convención + cache-buster
  del AMD; `upgrade.php` actualizará `config_plugins`).
- `php -l` local OK en `version.php`, `enviarnotas.php`, los 3 `lang/*.php`.

### Aceptación T5
- [x] Diagnóstico con evidencia curso 2 vs curso 4 (`_diag54.sh`, 27-oct) → causa
      raíz documentada (§5.1).
- [x] Mejora UX estado vacío: 3 lang + `enviarnotas.php` + JS + build min + bump
      (27-oct).
- [x] **Deploy v2026102701 (servidor, 27-oct tarde):** staging `_stage2701` (108
      archivos) → `docker cp` ruta nueva → `_inner6`: PRE_OK + BACKUP_OK
      (`mwa_dashboard.bak.2026102701` = v2026102601) + SWAP_OK + purge +
      `upgrade.php --non-interactive` → **`++ 2026102701: Éxito`**.
      ⚠️ Corte post-upgrade: mi one-liner de verificación usó `get_field(config_plugins,
      'version')` — **el version es FILA `(plugin,name='version',value)`, no columna**
      → PHP fatal → H4/H5/H6 (restart/smoke/markers) NO corrieron. Código desplegado
      igualmente; pendiente verificación con **`_diag55.sh`** (ya en el workspace).
- [x] **Verificación live** (2ª sesión 27-oct, `_diag56.sh`: `_diag55.sh` cortó la
      salida tras la sección A — quirk del runner; 56 lo reemplazó): version 2026102701
      + WS=20 + markers min (8/8) + 3 langs + HTTP 200/303 (course 2 y 4) +
      `docker restart` healthy ✔; backup `mwa_dashboard.bak.2026102701` presente ✔.
- [ ] **Usuario:** confirmación del hint `evalselect_empty` visible en curso 4
      (visible desde el deploy hasta que se desoculten los ítems).
- [ ] **Docente/usuario:** desocultar los quizzes del curso 4 en Calificaciones →
      selector de actividad + nota mínima operativos (paradigma curso 2).
- [x] **Push GitHub `main`:** commit `ee72bf0` (8 archivos: código T5 + plan) —
      `45fe4ae..ee72bf0` verificado 27-oct.

---

## T6 — "Enviar notas" con ítems OCultos en la libreta (curso 4) — **EN PROGRESO** (27-oct)

> **DECISIÓN USUARIO (27-oct, segunda sesión):** "Cambiar el plugin (Recomendado)" —
> los ítems `hidden=1` de la libreta quedan **SELECCIONABLES** en "Enviar notas"
> (marcados "(oculta)"); el docente puede verlos, fijar nota mínima, previsualizar y
> **enviarlos por email** SIN desocultar la libreta (desocultar también los expondría
> a estudiantes en la libreta — no siempre es lo que se quiere). La página del curso 4
> deja así de depender de la acción docente de desocultar.

### 6.1 Alcance acordado (27-oct)
- **Cambia (flujos de envío, incluyen ocultos):** WS `get_grades` (roster de la página —
  el JS mapea `act_{seq}` de las filas de estudiante contra el sel de send-options) +
  WS `get_grades_send_options` (selector + nota mínima) + preview y envío
  (`build_grades_mail`). WYSIWYG garantizado: la preview MUESTRA las notas ocultas,
  el docente las ve ANTES de enviar.
- **SIGUE SIN CAMBIAR (mantener excluidos los ocultos — diseño aprobado por el usuario):**
  tablero principal, snapshots (`snapshot_manager`), export. Por eso el flag
  `$includehidden` nace en `api::get_grades()` con **default `false`**.

### 6.2 Evidencia nueva (27-oct, 2ª sesión)
- **Moodle 5.0.1 (live):** `{external_functions}` GUARDA SOLO METADATA (id, name,
  classname, methodname, classpath, component, capabilities, services) — **NO persiste
  parámetros/return**. Las estructuras se resuelven en runtime contra el PHP del plugin
  (Moodle 3.x+). **Conclusión:** cambiar firmas/estructuras de WS es **seguro en el
  deploy** (bump de versión + `upgrade.php` refresca metadata con
  `external_update_descriptions()`, `lib/upgradelib.php:1191`). Verificado `_diag57/58/59`.
- **Curso 4 SIGUE `hidden=1` en los 4 quizzes** (gi#9,10,11,12; re-verificado `_diag56`
  §C) → el usuario no los desocultó; T6 los deja enviocables sin desocultar.
- Nota quiz en curso 4: `gradepass=0` en los 4 → la nota mínima saldrá por el fallback
  50% de la máxima (hint `passmin_fallback` existente) — el campo seguirá **editable**
  (comportamiento actual), igual que en curso 2.
- Runner quirk: la salida de `_diag55.sh` se cortó tras la sección A (SSH_EXIT:0, el
  probe simple funciona); `_diag56.sh` la cubrió completa. Si se repita: dividir
  secciones / evitar el bloque PHP largo al inicio.

### 6.3 Diseño de implementación (v2026102702) — ✅ TODO IMPLEMENTADO (27-oct, sesión 3)
1. **`classes/api.php` → `get_grades(int $courseid, int $groupid = 0, bool $includehidden = false)`**
   - ✅ firma + docblock `@param $includehidden`.
   - ✅ filtro de ítems: `return $item->itemtype === 'mod' && ($includehidden || !$item->hidden);`
     (con comentario T6 explicando que dashboard/snapshots/export siguen excluyendo).
   - ✅ meta por ítem: `$actnames['act_hidden_' . $seq] = (int)($item->hidden ?? 0);`
2. **`classes/external.php`:** ✅
   - `get_grades_parameters()` + `get_grades(...)`: `includehidden` (`PARAM_BOOL`,
     `VALUE_DEFAULT` false, **AL FINAL**) → `api::get_grades($cid, $gid, $includehidden)`.
   - `get_grades_send_options()`: `api::get_grades(..., true)`; `'hidden' => (bool)($meta['act_hidden_'.$i] ?? 0)`;
     `'hidden' => 0` **QUITADO** de la query a `grade_items` (comment inline T6);
     `'hidden' => new \external_value(PARAM_BOOL, ...)` en `_returns()`.
   - `build_grades_mail()`: `api::get_grades($courseid, $groupid, true)` + docblock actualizado.
3. **`amd/src/enviarnotas.js`:** ✅ `loadInitial()` roster con `includehidden: true`;
   `populateActivities()` etiqueta `t('act_hidden_tag')` cuando `a.hidden`;
   comentario de `refreshPassmarkHint()` ajustado (T5/T6). **AMD regenerado** (terser
   5.51.2, `--comments jsdoc`, `node --check` OK; **12/12 markers**: los 9 previos +
   `act_hidden_tag`, `includehidden`, `act_hidden_`).
4. **lang en/es/pt_br:** ✅ `gradesmail_act_hidden_tag` ("(oculta en la libreta)" /
   "(hidden in the gradebook)" / "(oculta na libreta)") + `gradesmail_evalselect_empty`
   adaptada (solo si NO HAY ningún ítem con nota en absoluto).
5. **`enviarnotas.php`:** ✅ `'act_hidden_tag'` en `$gmkeys`.
6. **`tests/send_grades_mail_test.php`:** ✅
   - `test_email_excludes_hidden_grade_items` → **`test_email_preview_includes_hidden_grade_items`**
     (aserción invertida: el 9.9 del assign oculto SÍ está en el preview).
   - NUEVO `test_api_default_still_excludes_hidden` (sin flag: 1 act; con flag: 2 acts).
   - NUEVO `test_send_options_exposes_hidden_flag` (hidden=true + max 10 + passmark 5).
   - Helper nuevo `count_activity_meta()`. `php -l` OK en los 8 PHP tocados.
7. **`version.php` → 2026102702** ✅.

### 6.4 Deploy + verificación (procedimiento verificado `_depl55`+inner)
1. Robocopy staging → `_stage2702_block_mwa_dashboard` (sin `.git`, sin `stage*` anidado)
   → `scp` a host `/tmp/_stage2702_...` (ruta NUEVA en el kontainer).
2. Inner: PRE_OK (markers) → `BACKUP_OK` `cp -a` → `mwa_dashboard.bak.2026102702`
   (conserva v2026102701) → SWAP_OK (verificar post) → `purge_caches` →
   `upgrade.php --non-interactive` → verificación **con la query correcta**
   (`get_field("config_plugins","value",[plugin, name=>'version'])`).
3. Smoke: root 200; `enviarnotas.php?course=4` → 303 (sin follow).
4. **CHECK FUNCIONAL EN EL SERVER (PHP CLI, como docente — id de `teacherid` en
   `mdl_block_mwa_dashboard_messages` o rol `editingteacher` del curso):**
   `external::get_grades_send_options(4)` → 3–4 actividades con `hidden=true`,
   `grademax>0`, `passmark>0`; y `get_grades_mail_preview(4, <alumno con nota>)` →
   el HTML contiene los nombres de los quizzes ocultos.
5. **Aceptación (usuario):** curso 4 → selector muestra los quizzes con "(oculta)",
   campo nota mínima activo (fallback 50%), preview muestra las notas, 1 envío real.
6. Push GitHub `main` (repo `Christian-Pemberty/Dashboard-Moodle`).

### 6.5 Estado al cierre de sesión 27-oct (corte por tokens) → **ACTUALIZADO sesión 3**
- [x] T5: v2026102701 **verificación live COMPLETA** (`_diag56.sh`) — cerrado.
- [x] T6: decisión del usuario + alcance + verificación de factibilidad WS (Moodle 5).
- [x] T6: `api.php` — firma + docblock.
- [x] T6: resto de §6.3 (filtro+meta api, external, JS, lang, $gmkeys, tests, version, AMD)
  → **HECHO sesión 3 (27-oct noche)**.

### 6.6 Deploy + verificación live v2026102702 (✅ sesión 3, 27-oct noche)
- [x] Staging local `_stage2702_block_mwa_dashboard` (robocopy, **108 archivos**, sin
      `.git`, sin `stage*` anidado, version 2026102702) → `scp` a host `/tmp/_stage2702…`.
- [x] `_depl56.sh` (host) + `_inner7.sh` (contenedor, base64 en línea): **PRE_OK →
      BACKUP_OK** (`cp -a` → `mwa_dashboard.bak.2026102702` conserva v2026102701) →
      **SWAP_OK** (markers `act_hidden_tag`/`includehidden` en min) → `purge_caches` OK →
      **`upgrade.php --non-interactive` OK: `++ 2026102702: Éxito`** → verificación DB:
      **`config_plugins version=2026102702 | WS=20`** (con la query correcta: version es
      FILA `name='version'`) → `INNER_DONE rc=0`.
- [x] **H4/H5 (restart + smoke) ejecutados** en `_depl56b.sh` (ver lección §6.7):
      `moodle_app | Up (healthy)`; HTTP root **200**, course 2 y 4 **200/303**.
- [x] `_diag60.sh` live: version=2026102702 + WS=20 · container healthy · markers LIVE
      (min `act_hidden_tag`+`includehidden`, api `act_hidden_`, external `act_hidden`,
      lang en/es/pt_br, version) ✔ · **NO hay PHPUnit en el contenedor** (vendor ausente)
      → los tests quedaron verificados por `php -l` local + check funcional CLI.
- [~] Check funcional CLI (send_options/preview del curso 4): probe v5 corrió hasta
      diagnosticar usuarios/roles pero **NO llegó a ejecutar las WS** (no se identificó
      una cuenta con capability en la cadena de contextos 45→3→1; el docente real accede
      desde la UI con credenciales propias). **Decisión usuario: dejar aquí** — la
      aceptación se hace en el navegador (§6.7).

### 6.7 LECCIONES NUEVAS (sesión 3, 27-oct noche)
1. **`docker exec -i` dentro de un orquestador que corre VÍA STDIN se come el resto
   del orquestador** (`_runssh.ps1` envía el script por stdin → `docker exec -i` hereda
   ese stdin → bash termina en EOF = corte silencioso tras el inner, exit 0 aparente).
   El T5 igualmente perdió H4-H6 por esto (no por el PHP fatal del one-liner).
   **Regla:** ejecutar orquestadores como ARCHIVO en el host
   (`bash /tmp/_depl56b.sh < /dev/null`) — nunca vía el runner stdin.
2. **`get_records_sql` usa la primera columna como índice**: si no es única, Moodle
   colapsa filas y `$row` queda como `stdClass` → "could not be converted to string".
   Siempre nombrar la primera columna única (p. ej. `SELECT ra.id, ...`).
3. **Contexto de curso ≠ id de curso**: `context_course::instance($cid)->id` (curso 4 →
   contexto **45**; cadena: 45 → cat 3 → system 1). Las queries de roles/capabilities
   deben ir contra los CONTEXTOS, no contra el courseid.
4. `set_user()` **NO existe** en Moodle 5.0.1 CLI → usar `$USER = $DB->get_record('user', ['id'=>…])`.
5. Diagnóstico de la cuenta docente (pendiente para la próxima sesión): el site tiene
   user 2 `admin` (soporte.mym), user 3 `mym` (coursecreator @ system), user 1 guest;
   **no se halló un `archetype-admin` con role_assignment en ctx=1** → para probes CLI
   futuros, pedir el usuario exacto al user o buscar `role_capabilities` ALLOW.
6. El `vendor` del Moodle 5.0.1 de producción **no trae PHPUnit** → los PHPUnit del
   plugin solo pueden correr en un entorno de desarrollo con Moodle + vendor.

### 6.8 Aceptación T6 (PENDIENTE — usuario, navegador, curso 4)
- [ ] Selector "Actividad evaluada" muestra los quizzes **con `(oculta en la libreta)`**
      (antes: deshabilitado con hint `evalselect_empty`).
- [ ] Campo "Nota mínima" activo (fallback 50% de la máxima, editable).
- [ ] Preview WYSIWYG muestra las notas de los quizzes ocultas por estudiante.
- [ ] 1 envío real (canal email vía B OK desde el §T4) + auditoría `status=ok`.
- [ ] Tablero del curso (fuera de "Enviar notas") **NO** cambió: sigue excluyendo ocultos.
- [ ] Push GitHub `main` (repo `Christian-Pemberty/Dashboard-Moodle`).

---

## T7 — Banner institucional en el correo de notas (NUEVA, 27-oct sesión 4)

> **Pedida del usuario (27-oct):** "¿es posible hacer que los mensajes salgan con un
> banner?" + 2 capturas: (a) el correo tal como llega hoy (sin banner), (b) cómo
> quiere llegarlo: banner institucional Uniclaritiana **sobre el saludo**, ancho
> completo, con su franja amarilla. "Yo ya lo tengo listo" → el usuario
> proporcionó el archivo: `block_mwa_dashboard/Banner/Banner.png`
> (**1471×285 px, RGBA, 730 KB**, esquinas mínimamente transparentes → PNG,
> no JPG).

### 7.1 Diseño
- **Archivo:** el banner vive en **`pix/banner.png`** del plugin (mover
  `Banner/Banner.png` → `pix/banner.png`; `Banner/` eliminado). Las `pix/` de un
  bloque Moodle son **públicas sin login** (verificado en vivo 27-oct:
  `logo.png` y `enviando-email.gif` → HTTP 200 sin sesión; `banner.png` era 404
  antes del deploy) → los clientes de correo (Gmail/Outlook, sin sesión) la
  cargan bien.
- **Render (email-safe):** al inicio del cuerpo de `build_grades_mail()`,
  **antes del saludo**, un `<img>` inline (sin CSS, sin `<style>`):
  - attributes: `width="600"` (motor Word/Outlook desktop usa el attr)
  - style: `display:block;width:100%;max-width:600px;height:auto;margin:0 0 16px;border:0;`
  - `alt` = cadena `gradesmail_banner_alt` (por idioma).
  - **WYSIWYG garantizado:** preview (`get_grades_mail_preview`) y envío
    (`build_grades_mail`) comparten la misma función → el docente ve EXACTAMENTE
    lo que se envía. El plain-text deriva de ese HTML (`html_to_text`) → no
    regresa a ser dominante.
- **Configuración (admin):** `settings.php` → `admin_setting_configtext
  block_mwa_dashboard/gradesmail_banner` (PARAM_URL, default vacío):
  - **vacío** → usa `pix/banner.png` del plugin (URL resuelta con
    `\moodle_url` → siempre la wwwroot real: `https://medicionra.
    uniclaretiana.edu.co/blocks/mwa_dashboard/pix/banner.png`);
  - **URL pública** → se usa tal cual (campaños/estacionales);
  - **literal `none`** → correo sin banner.
- **Cadenas (en/es/pt_br):** `gradesmail_banner_alt`,
  `settings_gradesmail_banner`, `settings_gradesmail_banner_desc`.
- **Tests (2 nuevos, `tests/send_grades_mail_test.php`):**
  - `test_mail_html_includes_banner_by_default` (banner presente, inline
    `max-width:600px;height:auto`, sin `<style>`, POSICIÓN sobre el saludo).
  - `test_mail_banner_setting_override_and_disable` (URL custom se respeta;
    `none` → cero `<img>` en el correo).

### 7.2 Código tocado (v2026102703)
1. `pix/banner.png` (nuevo, 730 KB) + `Banner/` eliminado.
2. `classes/external.php` → `build_grades_mail()`: bloque banner (T7) antes del
   saludo (get_config + `\moodle_url` + `<img>` inline).
3. `settings.php` → `admin_setting_configtext gradesmail_banner`.
4. `lang/{en,es,pt_br}` → 3 cadenas nuevas (alt + nombre/desc de la setting).
5. `tests/send_grades_mail_test.php` → 2 tests T7.
6. `version.php` → **2026102703**.
7. `php -l` OK en los 7 PHP tocados (local, PHP 8.3.30 Laragon).
8. **AMD: sin cambios** (sin JS nuevo → no se regenera el min).

### 7.3 Deploy + verificación (procedimiento probado `_depl56`/`_inner7`) — ✅ HECHO (27-oct sesión 4)
- [x] Staging `_stage2703_block_mwa_dashboard` (robocopy, **109 archivos con `pix/banner.png`**)
      → `scp` → host `/tmp/_stage2703_block_mwa_dashboard`.
- [x] `_depl57.sh` + `_inner8.sh` (orquestador **ejecutado como archivo**, `</dev/null`):
      PRE_OK → **BACKUP_OK** (`mwa_dashboard.bak.2026102703` conserva v2026102702) →
      SWAP_OK (version 2026102703 + `pix/banner.png` presente) → `purge_caches` →
      **`++ 2026102703: Éxito`** → `Nueva configuración: block_mwa_dashboard/
      gradesmail_banner` (la setting se registro sola en upgrade) → DB **con la
      query de fila correcta**: `version=2026102703 | WS=20`.
- [x] Restart `moodle_app` healthy + smoke HTTP: root **200**, course 2 **303**.
- [x] **Verificación pública del banner:** `curl .../blocks/mwa_dashboard/pix/banner.png`
      → **200** (sin login; pre-deploy era 404, post 200 con 730767 bytes).
- [x] Markers LIVE: `gradesmail_banner` (external + settings + lang ×3) +
      `pix/banner.png` (external + en live `pix/`) + version 2026102703.
- [~] **Check funcional CLI** (preview HTML con banner ANTES del saludo):
      **no corrió** (el usuario se fue); receta lista para la próxima sesión
      (como admin, curso 2, `get_grades_mail_preview`).

### 7.4 Aceptación T7 (usuario)
- [ ] Preview in-app (enviarnotas): el banner aparece ENCIMA del saludo,
      ancho completo, aspecto igual a su captura objetivo.
- [ ] **1 envío real** → en la bandeja (Gmail/Outlook) el correo llega **igual**
      que la preview (con banner, tabla bordes, nota coloreada) + auditoría
      `status=ok`.
- [ ] (Opcional) cambiar la setting a `none` → correo sin banner; a URL custom →
      esa imagen (solo si se desea probar; default = `pix/banner.png`).
- [ ] Push `main` de GitHub (repo `Christian-Pemberty/Dashboard-Moodle`).

---

## T7.1 — Banner a ANCHO COMPLETO / responsivo (NUEVA, 28-oct)

> **Pedida del usuario (28-oct, con captura de la preview):** "hacer que el banner
> abarque el ancho y que sea responsivo, porque queda más pequeño que la tabla de
> notas". Evidencia (captura "Vista previa del correo"): el `<img>` del banner
> mide ~600px pero la tabla ACT/NOTA ocupa todo el ancho de la tarjeta.
> **Causa raíz:** el render T7 (plan §7.1) puso `max-width:600px` en el `style` del
> `<img>` (conservadismo para Outlook) mientras la tabla usa `width="100%"` →
> cualquier renderizado web (preview in-app, Gmail, Outlook web) muestra banner
> 600px < tabla 100%.

### 7.1.1 Decisión de diseño (28-oct)

| Cliente | Comportamiento ANTES | Comportamiento DESPUÉS (T7.1) |
|---|---|---|
| Preview in-app | 600px (cap `max-width`) | **100%** del ancho de la tarjeta (igual que la tabla) |
| Gmail / Outlook web / Apple Mail | 600px | **100%** del contenido (≈ el ancho de la tabla → idéntico) |
| Outlook desktop (motor Word) | 600px (atributo `width`) | 600px (atributo `width="600"` **conservado**: el Word-engine ignora % y sin el attribute renderizaría el original 1471px → desborde) |

- **Regla:** `style="display:block;width:100%;height:auto;margin:0 0 16px;border:0;"`
  (el `max-width:600px` SALE del style); el atributo `width="600"` **se queda** solo
  como fallback del motor Word. `height:auto` conserva la proporción (1471×285) →
  responsivo en móvil también.
- WYSIWYG intacto: preview y envío comparten `build_grades_mail()` → el docente ve
  exactamente lo que llega. El plain-text no cambia (`html_to_text` sobre el mismo HTML).
- **Settings (`gradesmail_banner`) sin tocar:** vacío=plugin (`pix/banner.png`),
  URL pública=custom, `none`=sin banner — todas heredan el nuevo ancho automático.

### 7.1.2 Código tocado (v2026102801) — ✅ LOCAL (28-oct)

1. `classes/external.php` → `build_grades_mail()`: bloque banner — comentario actualizado
   (T7.1 con justificación) + `style` sin `max-width:600px` (atributo `width="600"` queda).
2. `tests/send_grades_mail_test.php` → `test_mail_html_includes_banner_by_default`:
   assertion `max-width:600px;height:auto` → `style="display:block;width:100%;height:auto;
   margin:0 0 16px;border:0;"` + `assertStringNotContainsString('max-width:600px')`.
   (El test de override/`none` no asertaba el tamaño → no cambia.)
3. `version.php` → **2026102703 → 2026102801** (convención del proyecto: bump +
   `upgrade.php` en el deploy; también cache-buster de assets del plugin).
4. `php -l` sobre `classes/external.php`, `tests/send_grades_mail_test.php`,
   `version.php`.
5. **Fix hallado por el probe (28-oct):** `get_grades_mail_preview()` L1043 —
   `$SESSION->userid` sin property en contexto CLI → PHP warning (inocua en web,
   ruido en logs/CLI). Guard: `($SESSION && !empty($SESSION->userid)) ? : null`.
   Subido como hotfix de 1 archivo (`_hotfix28.sh`), sha256 live = local
   (`f7f8a02b…`).
6. **AMD / lang / settings: SIN cambios** (no hay cadenas ni JS new → no se regenera min).

### 7.1.3 Git (estado local 28-oct)

- Commit A `9a2b174` = **T7 tal como está en vivo** (estado 27-oct, v2026102703;
  9 archivos incl. `pix/banner.png`) — referencia exacta del servidor anterior.
- Commit B `957bb32` (T7.1) ✅ HECHO 28-oct y **push `main`**: `97df4b5..957bb32`
  (2 commits subidos: T7 + T7.1).

### 7.1.4 Deploy + verificación (procedimiento `_depl57`/`_inner8` con staging `_stage2801`) — ✅ HECHO (28-oct)

- [x] Staging `_stage2801_block_mwa_dashboard` (robocopy, 109 archivos, sin `.git`, sin
      `stage*` anidado; version 2026102801; T7.1 style presente, sin `max-width:600px`)
      → `scp` a host `/tmp` (nombre NEW) rc=0.
- [x] `_depl58.sh` + `_inner9.sh` (orquestador `bash /tmp/_depl58.sh < /dev/null`, lección
      6.7-1): **PRE_OK → BACKUP_OK** (`mwa_dashboard.bak.2026102801` conserva v2026102703
      con `pix/banner.png`) → **SWAP_OK** → `purge_caches` →
      **`++ 2026102801: Éxito (0,02 s)`** → DB `config_plugins version=2026102801 | WS=20`.
- [x] Restart healthy (`Up 12s (healthy)`) + HTTP: root **200** · `enviarnotas.php?course=2`
      **303** · `pix/banner.png` **200** (aún pública).
- [x] Markers LIVE: `pix/banner.png` OK · `width:100%;height:auto` OK · **sin `max-width:600px`**
      OK · settings + lang en/es/pt_br OK · `version.php = 2026102801`.
- [x] **HOTFIX de 1 archivo (`_hotfix28.sh`):** guard `$SESSION->userid` (warning CLI, §7.1.2-5)
      → sha256 live = local `f7f8a02b…` (post-hotfix, el staging local también se sincronizó).
- [x] **CHECK FUNCIONAL CLI** (probe `_probe28b`, admin, curso 2, alumno uid 4 "Student One")
      → **PROBE_ALL_PASS** (6/6): banner presente · style `width:100%;height:auto` · attr
      `width="600"` · **sin `max-width:600px`** · `display:block` · banner ANTES del saludo;
      **sin warnings PHP**. Evidencia (el `src` en CLI sale con la wwwroot interna; en web
      es el dominio real):
      ```html
      <img src="…/blocks/mwa_dashboard/pix/banner.png" alt="Uniclaritiana – Fundación
           Universitaria Claretiana" width="600"
           style="display:block;width:100%;height:auto;margin:0 0 16px;border:0;">
      ```
- [x] Limpieza: probes borrados del contenedor con `-u root` (sticky-bit); host `/tmp`:
      quedan `_stage2801…`, `_depl58.sh`, `_inner9.sh`, `_probe28*.php`, `_run28.sh`,
      `_hotfix28.sh`, `_ext28_fix.php` (sticky-bit = no borrar como `proyectosmym`,
      requiere `sudo rm` — ver §12.6).
- [x] Push `main` (28-oct, `97df4b5..957bb32`).
- [ ] **Pendiente:** aceptación del usuario §7.1.5 (preview + envío real).

#### 7.1.4-bis — Banner COMPRIMIDO solo en producción (28-oct tarde, decisión usuario)

- El usuario re-comprimió `pix/banner.png`: **730 KB → 199 KB** (203,507 B, sigue
  1471×285 PNG). sha256 nueva: `0F700F80…76EB`.
- **Subido SOLO a producción por pedido expreso: "sin commit ni subirlo al git"**
  (`_img28.sh`: docker cp → live; sha256 live = esperada ✔).
- **Diagnóstico de visibilidad (`_diagg28.sh`):** origen real OK — vía IP del servidor
  (bypass CF) la URL sirve **NUEVO** (203,507 B). La URL pública por dominio
  servía aún la vieja (730,767 B, sha `f158dd6b…`) → **caché de borde de Cloudflare**
  retiene el PNG viejo hasta su TTL. No toca el servidor: se resuelve sola al expirar
  la caché CF, o con *Purge* en el dashboard de Cloudflare si se quiere inmediato.
  Impacto mientras tanto: zero visual (misma imagen), solo más bytes transferidos.
- **Estado git (intencional):** `pix/banner.png` del repo sigue siendo la 730 KB
  (commit `9a2b174`); en disco local queda la 199 KB como versión vigente (staging
  `_stage2801` también sincronizado). Regla para la próxima sesión: **nunca**
  redeployar `pix/banner.png` desde el repo (volvería a 730 KB); si un día se
  quiere versionar la compacta, commit + push como paso explícito del usuario.
- Sin commit/push de este cambio (pedido del usuario). Con esto se cierra la sesión.

### 7.1.5 Aceptación T7.1 (usuario, navegador)

- [ ] Preview in-app (`enviarnotas.php?course=2`): el banner ahora tiene **el MISMO ancho
      que la tabla** (tocando ambos bordes de la tarjeta), aspecto 1471×285 intacto.
- [ ] **1 envío real** → en Gmail/Outlook web el banner llega **a todo el ancho del
      contenido del correo**, igual que la tabla (WYSIWYG).
- [ ] (Opcional) comprobar en Outlook desktop 365: banner a 600px fijo (fallback Word,
      aceptado por diseño) — no regresa a 1471px desbordado.
- [ ] Estado `T7` (§7.4) se cierra CON este (misma característica).

---

## T8.1 — Refinamiento del editor del mensaje del docente (NUEVA, 28-oct sesión 2)

> **Pedidos del usuario (28-oct, con captura de la preview en modo edición):**
> 1. Los campos de edición (título "Mensaje Profesor:" y el cuerpo) **quedan muy
>    juntos** → "que los campos de edición no queden tan juntos, que estén un poco
>    más separados" (solo al momento de editar).
> 2. "que el mensaje del docente se alinee con el otro campo" → el campo del
>    **título debe alinearse con el del cuerpo** (hoy el título era un `<strong>`
>    a ancho de contenido: caja corta junto a una caja a ancho completo).
> 3. **Opción para NO enviar el mensaje:** hoy solo existe "Editar"/"Listo"; si el
>    docente decide no mandar mensaje, no hay forma de sacarlo del correo sin
>    re-crear la sección. El usuario propuso: (a) botón "Eliminar mensaje
>    docente" al lado de "Editar", o (b) una X en el campo de edición.
>
> **DECISIÓN (recomendada y aplicada — opción A, botón dedicado):**
> - Un **botón "Eliminar mensaje"** (`#mwaGmPreviewDelete`) en la cabecera de la
>   preview, **junto a** "Editar"/"Listo". Se muestra **solo mientras el mensaje
>   exista** (texto distinto de vacio O sección presente en la preview).
> - **Por qué no la X dentro del campo:** la X solo existiría en modo edición (el
>   docente podría no estar editando y querer borrar) y el campo de título es un
>   `<strong>` de una línea donde una X compite con el propio texto; el botón
>   dedicado es descubrible en cualquier estado y semánticamente limpio
>   ("Eliminar" = decidir no enviar; "Editar" = escribir/cambiar).
> - **Comportamiento (WYSIWYG):** al confirmar el modal, se limpian
>   `commentValue` y `commentLabelValue` (el título vuelve al default), se sale
>   del modo edición y la **sección desaparece de la preview**: el servidor ya
>   omite la sección completa cuando `comment` está vacio (`build_grades_mail()`,
>   `if ($comment !== '')`) → lo que se ve en la preview es EXACTAMENTE lo que
>   se envía. Se puede volver a escribir después con "Editar" (inyecta el ancla
>   vacía, como T1).
> - **Cero cambios de backend/WS:** el comportamiento del servidor (vacio = sin
>   sección) ya es el contrato de T8; T8.1 solo aplica JS + CSS + cadenas.
>
> ### T8.1.1 Cambios (v2026102803)
>
> 1. **`amd/src/enviarnotas.js`:**
>    - `buildUI()`: botón `#mwaGmPreviewDelete` (clase `mwa-gm-btn-del`) en la
>      cabecera de la preview, `style="display:none"` por defecto.
>    - `bindEls()`: `els.previewDelete`.
>    - Nuevos: `refreshPreviewDeleteBtn()` (visible si `commentValue` no vacío
>      O hay ancla `data-gm-editable="comment"` en el DOM; oculto si la card de
>      preview está oculta) y `deleteCommentMessage()` (sale del modo edición,
>      limpia `commentValue`/`commentLabelValue`, quita el ancla del DOM).
>    - `bindEvents()`: clic → `openConfirm(t('comment_delete_title'),
>      t('comment_delete_body'), deleteCommentMessage)` (mismo modal de toda la
>      app; la acción borra el borrador, merecía confirmación).
>    - `paintPreview()`: **sincronía 1:1 preview ↔ correo que se enviará** —
>      si `comment` vacío: la ancla residual de un HTML caché se **elimina** del
>      DOM (antes quedaba el título + caja vacía, que SÍ difiere del correo);
>      si `comment` no vacío pero el HTML caché no trae ancla: se **inyecta** el
>      ancla (mismo marcado del servidor) para mostrarla.
>    - `syncPreviewChips()`: al ocultar la card (selección vacía) también oculta
>      el botón.
> 2. **`styles/gradesmail.css`:**
>    - `mwa-gm-btn-del` (blanco/borde rojo suave, hover `#fdf1f1`) — destructivo
>      pero secundario, no compite con el azul primario de "Enviar".
>    - **Separación + alineación en modo edición** (solo in-app; el correo no
>      toca sus estilos inline email-safe):
>      ```css
>      [data-gm-editable="comment"] [data-gm-field="commentlabel"].mwa-gm-editing,
>      [data-gm-editable="comment"] [data-gm-field="commenttext"].mwa-gm-editing {
>          display: block; width: 100%; box-sizing: border-box; }
>      [...commentlabel].mwa-gm-editing { margin-bottom: .6rem; }
>      [...commenttext].mwa-gm-editing  { min-height: 2.6rem; }
>      ```
>      (el título pasa de caja a ancho de contenido a **a ancho completo**, alineado
>      con el cuerpo, con .6rem de aire entre ambos).
> 3. **`lang/{es,en,pt_br}`:** `gradesmail_preview_delete` ("Eliminar mensaje" /
>    "Delete message" / "Excluir mensagem"), `gradesmail_comment_delete_title`,
>    `gradesmail_comment_delete_body` (3 idiomas ×2).
> 4. **`enviarnotas.php`:** `$gmkeys` += `preview_delete`, `comment_delete_title`,
>    `comment_delete_body`.
> 5. **`amd/build/enviarnotas.min.js`:** regenerado (terser 5.51.2, `--compress
>    --mangle`); `node --check` OK ×2 (src + min); markers min: `mwaGmPreviewDelete`
>    ×2, `mwa-gm-btn-del` ×1, `preview_delete` ×1, `comment_delete_title` ×1,
>    regresiones `commentlabel` ×8 / `data-gm-editable` ×2.
> 6. **`version.php`:** 2026102802 → **2026102803**. `php -l` OK (enviarnotas,
>    version, lang ×3).
> 7. **Tests:** no se añade PHPUnit (cambio 100% de UI; el contrato "vacio = sin
>    sección" ya es del servidor y está cubierto por `test_send_includes_teacher_comment`
>    y `test_comment_label_editable_default_and_custom`, que asierten el ancla y el
>    default).
>
> ### T8.1.2 Deploy (procedimiento probado `_depl71`/`_inner10`) — ✅ VERIFICADO 28-oct (2ª sesión)
>
> - [x] Staging `_stage2803_block_mwa_dashboard` (robocopy `block_mwa_dashboard/`,
>       **109 archivos**, sin `.git`) → `scp` → host `/tmp/_stage2803_…`.
> - [x] `_depl72.sh` + `_inner11.sh`: **PRE_OK** → **BACKUP_OK**
>       (`mwa_dashboard.bak.2026102803` conserva v2026102802) → **SWAP_OK** →
>       `purge_caches` → **`++ 2026102803: Éxito`** → DB **`version=2026102803 |
>       WS=20`** → restart **`Up 12s (healthy)`** → HTTP root **200**,
>       `enviarnotas.php?course=2/4` **200/303** → markers LIVE:
>       `mwaGmPreviewDelete` ×2 (PRE contable; H6 muestra `x1` por `grep -c`
>       de línea, misma quirk que `_depl71`) · `mwa-gm-btn-del` en min y en
>       `styles/gradesmail.css` (regla `min-height: 2.6rem` OK) ·
>       `preview_delete` + `comment_delete_title` en min y en `enviarnotas.php`
>       (`$gmkeys`) · `gradesmail_preview_delete` + `comment_delete_body` en
>       lang ×3 · regresiones `commentlabel` / `mwaGmPreviewEdit` /
>       `act_hidden_tag` OK · banner live **203507 bytes** (= la compacta, no
>       regresó a 730 KB).
> - [x] LECCIONES nuevas (28-oct 2ª sesión):
>   1. **`grep -c` cuenta LÍNEAS, no OCURRENCIAS** → en el min de 1 línea
>      `grep -c "marker"` = 1 aunque el marker exista ×2. Para contar
>      ocurrenciones: `grep -o "marker" | wc -l` (el PRE de `_inner11` lo usa
>      bien; los H6 de T8 heredaron la imprecisión).
>   2. **Sticky-bit de `/tmp` del contenedor (reconfirmado):** `docker cp` crea
>      el staging dir como root → el `rm -rf` del paso H2 falla para uid 1001
>      ("Operation not permitted"). Limpieza con `docker exec -u root`
>      (mismo remedio que el del 27-oct; corregido en `_depl72.sh`).
>   3. **Fallo de PRE = 0 daño a producción:** el primer intento falló en PRE
>      (antes de BACKUP/SWAP) → live intacta en 2026102802; el "RESTORE
>      ORQUESTADOR" no hizo nada (no había backup aún). La doble verificación
>      PRE/restore sigue salvando.
>
> ### T8.1.3 Aceptación (usuario, navegador)
>
> - [ ] Preview → "Editar": los dos campos van **alineados al ancho completo**
>       y **con separación** entre ellos (captura anterior: caja del título corta
>       y pegada a la del cuerpo).
> - [ ] Con mensaje escrito aparece el botón **"Eliminar mensaje"** al lado de
>       "Editar"; al confirmarlo el mensaje **desaparece de la preview** (y el
>       envío real llega **sin la sección**, WYSIWYG).
> - [ ] Con el mensaje eliminado y en modo "Editar" de nuevo, el área vuelve a
>       poder escribirse (ancla inyectada vacía, título por defecto).
> - [ ] 1 envío real SÍ con mensaje, y 1 SIN (después de "Eliminar mensaje") —
>       ambos con auditoría `status=ok`.
> - [ ] Push `main` (repo `Christian-Pemberty/Dashboard-Moodle`).

---

## Cierre / riesgo (mantener)

- **Estado de datos curso 4 (27-oct, T5):** los 4 ítems `mod=quiz` están ocultos
  (`hidden=1`) en la libreta → el plugin (correctamente) no los publica ni ofrece
  como actividad del envío; hasta que el docente los desoculte, el selector aparece
  deshabilitado **con el hint explicativo** `evalselect_empty` (v2026102701).
  Si se repite en otros cursos: misma receta — desocultar ítems en Calificaciones.
  (Ver §T5 para evidencia de la comparación curso 2 vs 4.)

- ⚠️ **Dependencia externa del canal de correo (27-oct):** el envío real pasa por el relay
  `smtp-unicla` (contenedor Python en el host, puerto 1026) → Microsoft Graph
  (`app registration "Dspace"`, `Mail.Send` client_credentials). **Ese canal está roto
  (403 `ErrorAccessDenied` en los 3 senders + 404 `MailboxNotEnabledForRESTAPI` en
  `soporte.mym`)** → auditoría `status=error` y "no me deja enviar". **No es bug del
  plugin**. Última ventana funcional: **2026-10-26 14:04 UTC**; primer fallo
  **2026-10-27 13:18 UTC** (coincide con la queja). **RESUELTO 27-oct vía B** (fallback
  SMTP AUTH en el relay → 250 OK; ver §T4). La Vía A (reconceder la app en Azure AD) sigue
  pendiente con el admin M365: mientras no se haga, todo el envío real corre por SMTP
  como `soporte.cursosvirtuales@` (cuyas credenciales viven en `/app/.smtp_creds`, 600).
  ⚠️ **Cambio del remitente visto por los recipientes: ahora es
  `soporte.cursosvirtuales@`** (antes era a veces `soporte.mym@`). Verificación completa:
  `DIARIO-DESARROLLO-2026-10-27.md` §6.
- **T1 y T2 comparten `build_grades_mail()`:** hacer T1 → T2 en ese orden; T2 solo
  reestiliza, T1 solo ancla. Si se hace T2 primero, T1 sigue (el ancla se inyecta igual).
- El ancla `data-gm-editable` viaja en el HTML del correo recibido: inofensivo (los
  clientes de correo ignoran atributos `data-`) pero **decidir si mantenerlo en el HTML final**
  o inyectarlo solo en `get_grades_mail_preview()` (más limpio para el correo real).
  → Recomendación: inyectar el ancla solo en el preview (mismo HTML, wrapper JS que
  localiza el párrafo de comentario por la cadena `gradesmail_email_commentlabel`).
- Los colores de estado (T2) usan la clasificación pendiente/ok del frontend; asegurar
  que el umbral del "aprobado" en el correo no dependa del filtro de actividad (usar
  siempre "¿tiene nota ≠ pendiente?").
