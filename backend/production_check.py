"""Fail-fast checks before starting a staging/production Passenger app."""
import os
import sys


def fail(message: str) -> None:
    print(f"[FAIL] {message}")
    raise SystemExit(1)


def main() -> int:
    env = os.getenv("APP_ENV", "").strip().lower()
    db = os.getenv("DB_CONNECTION", "").strip().lower()
    session_secret = os.getenv("SESSION_SECRET", "")
    encryption_key = os.getenv("ENCRYPTION_KEY", "")
    cors = [x.strip() for x in os.getenv("CORS_ALLOWED_ORIGINS", "").split(",") if x.strip()]

    if env not in {"staging", "production"}:
        fail("APP_ENV must be staging or production")
    if db != "mysql":
        fail("Namecheap staging/production must use MySQL; SQLite is not allowed")
    if len(session_secret) < 32 or "replace" in session_secret.lower() or "change_me" in session_secret.lower():
        fail("SESSION_SECRET must be a real random secret with at least 32 characters")
    if len(encryption_key) < 32 or "replace" in encryption_key.lower() or "change_me" in encryption_key.lower():
        fail("ENCRYPTION_KEY must be a real random secret with at least 32 characters")
    if not os.getenv("DB_USERNAME") or not os.getenv("DB_PASSWORD") or not os.getenv("DB_DATABASE"):
        fail("DB_USERNAME, DB_PASSWORD and DB_DATABASE are required")
    if any("elawaady.com" in origin for origin in cors):
        fail("Live elawaady.com must not be enabled during staging")
    if not any("e-network.net" in origin for origin in cors):
        fail("Staging CORS must include e-network.net")

    print("[OK] production/staging environment checks passed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
