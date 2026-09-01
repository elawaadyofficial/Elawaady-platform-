#!/usr/bin/env python3
"""Offline probe for the staging-only WSGI fallback health contract."""
import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BACKEND = os.path.join(ROOT, "backend")
if BACKEND not in sys.path:
    sys.path.insert(0, BACKEND)

from staging_fallback import make_staging_fallback  # noqa: E402


def probe(path: str):
    captured = {}

    def start_response(status, headers):
        captured["status"] = status
        captured["headers"] = dict(headers)

    app = make_staging_fallback(ModuleNotFoundError("synthetic staging probe"))
    body = b"".join(app({"PATH_INFO": path}, start_response))
    captured["payload"] = json.loads(body.decode("utf-8"))
    return captured


def require(condition: bool, message: str):
    if not condition:
        raise AssertionError(message)


for health_path in ("/health", "/api/v1/health"):
    result = probe(health_path)
    require(result["status"].startswith("503 "), f"{health_path}: expected 503")
    require(result["payload"].get("ok") is False, f"{health_path}: ok must be false")
    require(result["payload"].get("status") == "bootstrap_incomplete", f"{health_path}: wrong state")
    require(result["payload"].get("environment") == "staging", f"{health_path}: wrong environment")
    require(result["headers"].get("Cache-Control") == "no-store", f"{health_path}: cache guard missing")
    require(result["headers"].get("X-Robots-Tag") == "noindex, nofollow", f"{health_path}: robots guard missing")
    serialized = json.dumps(result["payload"]).lower()
    for forbidden in ("password", "db_pass", "traceback", "secret", "credential"):
        require(forbidden not in serialized, f"{health_path}: sensitive marker leaked: {forbidden}")

unknown = probe("/orders")
require(unknown["status"].startswith("503 "), "unknown route must remain fail-closed")
require("not ready" in unknown["payload"].get("message", "").lower(), "unknown route diagnostic changed")

print("Staging fallback health probe: PASS")
