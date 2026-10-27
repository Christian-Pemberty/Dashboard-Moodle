import asyncio
import aiosmtpd.controller
import requests
import json
import logging
import email as emaillib
from email.header import decode_header
import os
import time

logging.basicConfig(level=logging.INFO)

TENANT_ID = os.environ.get("TENANT_ID", "498edaab-d830-428c-99a2-2890afb09c69")
CLIENT_ID = os.environ.get("CLIENT_ID", "9f47a629-be68-46e2-8e59-8f0b717cd79b")
# SECRET NO VERSIONADO (GitHub Push Protection lo detecto el 27-oct). El valor
# real vive SOLO en el contenedor vivo (smtp-unicla /app/app.py) y/o el env CLIENT_SECRET.
CLIENT_SECRET = os.environ.get("CLIENT_SECRET", "PASTE_AQUI_EL_SECRET_DEL_TENANT (ver README)")

# Mailboxes verified EXO-enabled (Graph users/{x}/sendMail => 202), in tenant order of preference.
# Overridable via env RELAY_FALLBACK (comma-separated).
DEFAULT_FALLBACKS = [
    "soporte.cursosvirtuales@uniclaretiana.edu.co",
    "desarrollador.mediosymediaciones@uniclaretiana.edu.co",
]
FALLBACK_SENDERS = [
    s.strip().lower()
    for s in os.environ.get("RELAY_FALLBACK", ",".join(DEFAULT_FALLBACKS)).split(",")
    if s.strip()
]


# ================= SMTP AUTH fallback (Via B, 27-oct) =================
# Ultimo eslabon cuando la cadena Graph falla. Credenciales por env
# (SMTP_FALLBACK_USER / SMTP_FALLBACK_PASS) o archivo /app/.smtp_creds
# (linea 1 = usuario, linea 2 = app password). Requiere restart tras editar.
SMTP_FALLBACK_HOST = os.environ.get("SMTP_FALLBACK_HOST", "smtp.office365.com").strip().lower()
SMTP_FALLBACK_PORT = int(os.environ.get("SMTP_FALLBACK_PORT", "587"))

def _load_smtp_creds():
    user = os.environ.get("SMTP_FALLBACK_USER", "").strip()
    psw  = os.environ.get("SMTP_FALLBACK_PASS", "").strip()
    try:
        with open("/app/.smtp_creds") as fh:
            lines = [l.strip() for l in fh.read().splitlines() if l.strip()]
        if len(lines) >= 1 and not user:
            user = lines[0]
        if len(lines) >= 2 and not psw:
            psw = lines[1]
    except FileNotFoundError:
        pass
    if user:
        user = user.lower()
    return user, psw

SMTP_FALLBACK_USER, SMTP_FALLBACK_PASS = _load_smtp_creds()

def smtp_fallback_send(raw_to, subject, html_body, text_body):
    import smtplib
    from email.mime.multipart import MIMEMultipart
    from email.mime.text import MIMEText
    from email.utils import formatdate
    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"] = SMTP_FALLBACK_USER
    msg["To"] = ", ".join(raw_to)
    msg["Date"] = formatdate(localtime=True)
    attached = False
    if text_body:
        msg.attach(MIMEText(text_body, "plain", "utf-8"))
        attached = True
    if html_body:
        msg.attach(MIMEText(html_body, "html", "utf-8"))
        attached = True
    if not attached:
        raise RuntimeError("sin cuerpo (text/html) para enviar por SMTP")
    with smtplib.SMTP(SMTP_FALLBACK_HOST, SMTP_FALLBACK_PORT, timeout=30) as s:
        s.starttls()
        s.login(SMTP_FALLBACK_USER, SMTP_FALLBACK_PASS)
        s.sendmail(SMTP_FALLBACK_USER, raw_to, msg.as_string())
    return SMTP_FALLBACK_USER
# ======================================================================

TOKEN_CACHE = {"token": None, "expires": 0}


def get_token():
    now = time.time()
    if TOKEN_CACHE["token"] and TOKEN_CACHE["expires"] > now + 30:
        return TOKEN_CACHE["token"]
    r = requests.post(
        f"https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/token",
        data={
            "client_id": CLIENT_ID,
            "client_secret": CLIENT_SECRET,
            "scope": "https://graph.microsoft.com/.default",
            "grant_type": "client_credentials",
        },
        timeout=30,
    )
    r.raise_for_status()
    j = r.json()
    TOKEN_CACHE["token"] = j["access_token"]
    TOKEN_CACHE["expires"] = now + int(j.get("expires_in", 3456))
    return TOKEN_CACHE["token"]


class GraphHandler:
    # aiosmtpd protocol hook: announce a big size limit (Moodle mails are small, but be nice)
    async def handle_HELO(self, server, session, envelope):
        return "250 smtp-unicla OK"

    async def handle_DATA(self, server, session, envelope):
        dynamic = envelope.mail_from.lower()
        senders = [dynamic] + [s for s in FALLBACK_SENDERS if s != dynamic]
        logging.info(f"Correo recibido de (MAIL FROM): {envelope.mail_from}")
        logging.info(f"Destinatarios: {list(envelope.rcpt_tos)}")
        logging.info(f"Cadena de remitentes a intentar: {senders}")

        # Parse subject / bodies (same logic as before)
        msg = emaillib.message_from_bytes(envelope.content)
        # Decode RFC 2047 encoded subjects (e.g. "=?utf-8?B?...?=") into plain text
        raw_subject = msg.get("Subject", "")
        decoded_subject = ""
        for part, charset in decode_header(raw_subject):
            if isinstance(part, bytes):
                decoded_subject += part.decode(charset or "utf-8", errors="replace")
            else:
                decoded_subject += part
        subject = decoded_subject.strip() or "Sin asunto"
        html_body = None
        text_body = None
        logging.info(f"Mensaje multipart: {msg.is_multipart()}")
        if msg.is_multipart():
            for part in msg.walk():
                ctype = part.get_content_type()
                cdisposition = str(part.get("Content-Disposition", ""))
                logging.info(f"Parte encontrada: {ctype}")
                if "attachment" in cdisposition.lower():
                    continue
                charset = part.get_content_charset() or "utf-8"
                if ctype == "text/html":
                    try:
                        html_body = part.get_payload(decode=True).decode(charset, errors="replace")
                    except Exception:
                        html_body = part.get_payload(decode=True).decode("utf-8", errors="replace")
                elif ctype == "text/plain":
                    try:
                        text_body = part.get_payload(decode=True).decode(charset, errors="replace")
                    except Exception:
                        text_body = part.get_payload(decode=True).decode("utf-8", errors="replace")
        else:
            charset = msg.get_content_charset() or "utf-8"
            payload = msg.get_payload(decode=True)
            if payload:
                try:
                    decoded = payload.decode(charset, errors="replace")
                except Exception:
                    decoded = payload.decode("utf-8", errors="replace")
                if msg.get_content_type() == "text/html":
                    html_body = decoded
                else:
                    text_body = decoded

        body = html_body or text_body or ""
        content_type = "html" if html_body else "text"

        recipients = [
            {"emailAddress": {"address": r.lower()}}
            for r in envelope.rcpt_tos
        ]

        last_error = None
        for sender in senders:
            payload = {
                "message": {
                    "subject": subject,
                    "body": {"contentType": content_type, "content": body},
                    "from": {"emailAddress": {"address": sender}},
                    "toRecipients": recipients,
                },
                "saveToSentItems": True,
            }
            try:
                token = get_token()
                logging.info(f"Intentando enviar como {sender}")
                r = requests.post(
                    f"https://graph.microsoft.com/v1.0/users/{sender}/sendMail",
                    headers={
                        "Authorization": f"Bearer {token}",
                        "Content-Type": "application/json",
                    },
                    data=json.dumps(payload),
                    timeout=30,
                )
            except requests.exceptions.RequestException as req_err:
                # Network-level failure: outcome unknown? No request completed -> NOT sent. Safe to try next.
                last_error = f"{sender}: {req_err}"
                logging.error(f"Error de red/transporte con {sender}: {req_err}")
                continue

            if 200 <= r.status_code < 300:
                logging.info(f"Correo enviado correctamente desde {sender} -> {list(envelope.rcpt_tos)}")
                return "250 OK (enviado via " + sender + ")"

            if r.status_code in (404, 403, 400):
                # Mailbox invalid / not EXO-enabled / invalid user: message NOT sent. Safe to fall through.
                last_error = f"{sender}: HTTP {r.status_code} {r.text[:200]}"
                logging.warning(f"Remitente inválido o sin habilitar (sin envío) {last_error}")
                continue

            # Other 4xx/5xx: unknown whether partially processed -> stop, report failure.
            last_error = f"{sender}: HTTP {r.status_code} {r.text[:300]}"
            logging.error(f"Error Graph API: {last_error}")
            break

        # === SMTP AUTH fallback (Via B, 27-oct): Graph caido -> SMTP directo a M365 ===
        if SMTP_FALLBACK_USER and SMTP_FALLBACK_PASS:
            try:
                raw_to = [r["emailAddress"]["address"] for r in recipients]
                logging.info(f"Intentando SMTP AUTH fallback como {SMTP_FALLBACK_USER} -> {raw_to}")
                sent_via = smtp_fallback_send(raw_to, subject, html_body, text_body)
                logging.info(f"Correo enviado correctamente POR SMTP FALLBACK como {sent_via} -> {raw_to}")
                return "250 OK (enviado via SMTP fallback " + sent_via + ")"
            except Exception as smtp_err:
                logging.error(f"SMTP fallback fallo: {type(smtp_err).__name__}: {smtp_err}")
                return "500 Error (Graph caido y SMTP fallback fallo: " + str(smtp_err)[:150] + ")"
        logging.error(f"TODOS los remitentes fallaron. Último error: {last_error}")
        return "500 Error"


async def main():
    controller = aiosmtpd.controller.Controller(
        GraphHandler(),
        hostname="0.0.0.0",
        port=1025,
    )
    controller.start()
    logging.info("SMTP relay escuchando en puerto 1025 (fallbacks: " + ", ".join(FALLBACK_SENDERS) + ")")
    await asyncio.Event().wait()


if __name__ == "__main__":
    asyncio.run(main())
