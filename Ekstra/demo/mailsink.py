"""Demo mail catcher.

The platform sends mail through PHPMailer over SMTP. In the public demo that
whole path still runs — EHLO, AUTH, DATA, the templates, the attachments — but
the message stops here and is written to a file instead of reaching the real
internet. An anonymous demo wired to a live SMTP account is a spam relay with
extra steps; this keeps the feature demonstrable and useless to abuse.
"""

import os
import threading
import time

from aiosmtpd.controller import Controller
from aiosmtpd.smtp import AuthResult

MAILDIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "mail")
KEEP = 200


def accept_anything(server, session, envelope, mechanism, auth_data):
    """The demo has no real accounts, so any credentials are fine."""
    return AuthResult(success=True)


class Sink:
    async def handle_DATA(self, server, session, envelope):
        os.makedirs(MAILDIR, exist_ok=True)
        name = "%.6f.eml" % time.time()
        path = os.path.join(MAILDIR, name)
        with open(path, "wb") as f:
            f.write(b"X-Demo-To: " + ", ".join(envelope.rcpt_tos).encode() + b"\r\n")
            f.write(b"X-Demo-From: " + envelope.mail_from.encode() + b"\r\n")
            f.write(envelope.content)

        # keep the directory from growing without bound
        files = sorted(os.listdir(MAILDIR))
        for old in files[:-KEEP]:
            try:
                os.remove(os.path.join(MAILDIR, old))
            except OSError:
                pass

        print("captured %s -> %s" % (envelope.mail_from, envelope.rcpt_tos), flush=True)
        return "250 Message accepted for delivery"


if __name__ == "__main__":
    controller = Controller(
        Sink(),
        hostname="127.0.0.1",
        port=1025,
        authenticator=accept_anything,
        auth_require_tls=False,
    )
    controller.start()   # runs its own loop in a background thread
    print("mail sink listening on 127.0.0.1:1025 -> %s" % MAILDIR, flush=True)
    try:
        threading.Event().wait()
    except KeyboardInterrupt:
        controller.stop()
