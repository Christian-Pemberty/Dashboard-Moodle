# Respaldo del relay `smtp-unicla` (canal de correo MWA → Microsoft 365)

> **Por qué está aquí:** el relay corre como contenedor Docker **fuera** del repo del
> plugin (no es código de `block_mwa_dashboard`), pero es **parte crítica de la cadena
> de envío real de correos** de la app "enviarnotas". Este folder guarda copias exactas
> (verificadas por MD5) para que nunca se pierda la lógica de envío (Vía A Graph + Vía B
> fallback SMTP AUTH) ni se dificulte un restore.

## Archivos

| Archivo | Versión | MD5 (vivo en contenedor) | Qué contiene |
|---|---|---|---|
| `app.py` | **Actual en producción** (con Vía B) — 27-oct-2026 | `93cb39691a4c137a4ee414801e323867` | Relay completo: SMTP `aiosmtpd` (1025→host 1026), cadena Graph
   `sendMail` (3 senders) + **fallback SMTP AUTH** a `smtp.office365.com:587`. |
| `app.py.orig.20261021.bak` | **Original** (solo Graph, sin Vía B) | `76e246bd9f85f92fa1bffd79bc536956` | El relay como estaba antes del 27-oct. **Usar solo para rollback.** |

> **⚠️ Diferencia única entre la copia del repo y la viva en el contenedor:** la
> línea del `CLIENT_SECRET` (Azure, tenant M365). GitHub **Push Protection bloqueó el
> push original (27-oct)** por ese secreto → en las copias del repo hay un **placeholder
> `PASTE_AQUI_EL_SECRET_DEL_TENANT`** (línea 15-17) en lugar del valor real. El MD5 de
> arriba es el del archivo **vivo en el contenedor** (idéntico al repo salvo esa línea).
> El secreto real sigue viviendo SOLO en `smtp-unicla:/app/app.py` (y con el admin M365).

## Dónde vive en producción

- Servidor: `192.168.25.4` (user `proyectosmym`, llave SSH `~/.ssh/mwa_deploy`).
- Contenedor: `smtp-unicla` (imagen `smtp-unicla`, `RestartPolicy=always`).
- Ruta dentro del contenedor: `/app/app.py` (workdir `/app`, cmd `python app.py`).
- Puerto: contenedor `1025/tcp` → host `1026` (Moodle apunta a `172.18.0.1:1026` /
  `127.0.0.1:1026` según red; ver `smtphosts` en la config de Moodle).
- **Credenciales SMTP (NO están en este repo):** `/app/.smtp_creds` (permiso 600) —
  línea 1 = `soporte.cursosvirtuales@uniclaretiana.edu.co`, línea 2 = app password.
  El relay también las acepta por env `SMTP_FALLBACK_USER` / `SMTP_FALLBACK_PASS`.
- Backup de emergencia en el propio contenedor: `/app/app.py.bak.20261027` (= original).

## Restaurar la versión en producción (Vía B) si se corrompe `/app/app.py`

> ⚠️ La copia del repo trae el `CLIENT_SECRET` como PLACEHOLDER (ver tabla de arriba).
> Antes de reemplazar, hay que **inyectar el secreto real** (una de las dos formas).

```bash
# 1) desde el WORKSPACE local (este folder):
scp -i ~/.ssh/mwa_deploy app.py proyectosmym@192.168.25.4:/tmp/relay_app.py
# 2) por SSH al server — inyectar el secreto real (elige UNA):
#    (a) extraerlo del archivo que aún vive en el contenedor (si existe):
docker exec smtp-unicla grep -n "CLIENT_SECRET = os.environ" /app/app.py | head -2
#      → si la línea real sigue ahí, copia el valor y sustitúyelo en /tmp/relay_app.py
#        (en el placeholder PASTE_AQUI_EL_SECRET_DEL_TENANT) antes del paso 3.
#    (b) fijarlo por env del contenedor (recomendado, evita re-editar el código):
#      el contenedor debe arrancar con CLIENT_SECRET=<secreto real> (lo tiene el admin M365
#      o se regenera en Azure AD → Application registrations → Dspace → Certificates & secrets).
# 3) desplegar:
docker cp /tmp/relay_app.py smtp-unicla:/app/app_new.py
docker exec smtp-unicla python -m py_compile /app/app_new.py   # SINTAX OK esperado
docker exec smtp-unicla sh -c 'cp -a /app/app_new.py /app/app.py && rm -f /app/app_new.py'
docker restart smtp-unicla
docker logs smtp-unicla --tail 5   # debe ver: "SMTP relay escuchando en puerto 1025"
rm -f /tmp/relay_app.py
```

## Rollback (volver a la versión original, sin Vía B) — SOLO si Vía B da problemas

Igual que arriba, pero copiar `app.py.orig.20261021.bak` a `smtp-unicla:/app/app.py`
(después de `py_compile`). **Consecuencia:** se pierde el fallback; si la cadena Graph
seguía caída (403/404 de la app "Dspace"), el envío real volvería a fallar hasta que el
admin M365 reconceda permisos (Vía A).

## Verificar que el envío funciona (sin tocar nada)

1. `docker logs smtp-unicla --tail` — tras un envío de Moodle, debe aparecer
   `Correo enviado correctamente POR SMTP FALLBACK como soporte.cursosvirtuales@…`
   (o `enviado via [sender]` si la cadena Graph ya funciona) y `250 OK`.
2. BD Moodle (tabla `mdl_block_mwa_dashboard_messages`): la fila nueva del envío debe
   traer `status=ok` + `moodle_msgid` (no `error`).

## Notas de seguridad e higiene

- **NUNCA** escribir en este repo (ni en ningún `.sh`/log): la app password de
  `soporte.cursosvirtuales@` (vive solo en `/app/.smtp_creds`, 600) ni el secreto de
  cliente Azure.
- Estado 27-oct: GitHub Push Protection **bloqueó el intento** de versionar el secreto
  (commit `1524043` rechazado y purgado del repo local `gc --prune=now`) → copias del
  repo con **placeholder**; el secreto sigue en `smtp-unicla:/app/app.py` vivo.
  **Recomendación:** migrar el secreto a variable de entorno del contenedor (y/o
  regenerarlo en Azure AD si se duda que haya sido expuesto) — entonces el `app.py`
  vivo y el del repo son idénticos y el backup se vuelve literal.
- La Vía A (reconceder consent de la app "Dspace") resuelve el canal de raíz sin tocar
  ninguno de estos archivos.
- Este repo es **privado** (`Christian-Pemberty/Dashboard-Moodle`); aún así, este
  folder contiene la **lógica completa del canal de correo** → si el repo cambiera de
  visibilidad, revisar antes este folder.
