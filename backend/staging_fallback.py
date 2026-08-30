"""Safe staging-only WSGI fallback for incomplete backend boots.

This module intentionally exposes no secrets, database credentials, stack traces,
or business data. It exists so a staging deployment can return a useful health
response while the reviewed backend source tree is still being imported.
"""
import json
from datetime import datetime, timezone


def make_staging_fallback(import_error: Exception):
    error_name = type(import_error).__name__

    def application(environ, start_response):
        path = environ.get("PATH_INFO", "/")
        payload = {
            "ok": False,
            "status": "bootstrap_incomplete",
            "environment": "staging",
            "service": "Elawaady XDigital backend",
            "message": "Reviewed backend source is still being assembled on the staging branch.",
            "missing_runtime": error_name,
            "timestamp": datetime.now(timezone.utc).isoformat(),
        }

        if path not in ("/", "/health", "/api/v1/health"):
            payload["message"] = "Staging backend is not ready to serve application routes yet."

        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        start_response(
            "503 Service Unavailable",
            [
                ("Content-Type", "application/json; charset=utf-8"),
                ("Content-Length", str(len(body))),
                ("Cache-Control", "no-store"),
                ("X-Robots-Tag", "noindex, nofollow"),
            ],
        )
        return [body]

    return application
