"""Fail-fast checks before starting a staging/production Passenger app."""
import importlib.util
import os
import sys
from pathlib import Path
from urllib.parse import urlparse

APP_ROOT = Path(__file__).resolve().parent
REQUIRED_BACKEND_MODULES = (
    "src/Api/App.py",
    "src/Api/WsgiAdapter.py",
    "src/Core/DatabaseFactory.py",
)


def fail(message: str) -> None:
    print(f"[FAIL] {message}")
    raise SystemExit(1)


def hostname(value: str) -> str:
    """Return a normalized hostname for an absolute http(s) URL, or an empty string."""
    parsed = urlparse(value)
    if parsed.scheme not in {"http", "https"}:
        return ""
    return (parsed.hostname or "").strip().lower().rstrip(".")


def host_is(host: str, domain: str) -> bool:
    """Match a domain or one of its real subdomains without substring spoofing."""
    domain = domain.lower().rstrip(".")
    return host == domain or host.endswith("." + domain)


def require_backend_tree() -> None:
    missing = [path for path in REQUIRED_BACKEND_MODULES if not (APP_ROOT / path).is_file()]
    if missing:
        fail(
            "Backend source tree is incomplete. Missing: " + ", ".join(missing)
        )

    migrations_dir = APP_ROOT / "database"
    if not migrations_dir.exists() or not any(migrations_dir.rglob("*.sql")):
        fail("Backend database schema/migrations are missing from backend/database")

    tests_dir = APP_ROOT / "tests"
    if not tests_dir.exists() or not any(tests_dir.rglob("test_*.py")):
        fail("Backend test suite is missing from backend/tests")


def require_runtime_dependencies() -> None:
    required_modules = {
        "pymysql": "pymysql",
        "requests": "requests",
        "dotenv": "python-dotenv",
        "argon2": "argon2-cffi",
        "cryptography": "cryptography",
        "PIL": "Pillow",
    }
    missing = [package for module, package in required_modules.items() if importlib.util.find_spec(module) is None]
    if missing:
        fail("Missing Python runtime packages: " + ", ".join(sorted(missing)))


def main() -> int:
    env = os.getenv("APP_ENV", "").strip().lower()
    app_url = os.getenv("APP_URL", "").strip().lower().rstrip("/")
    app_host = hostname(app_url)
    db = os.getenv("DB_CONNECTION", "").strip().lower()
    session_secret = os.getenv("SESSION_SECRET", "")
    encryption_key = os.getenv("ENCRYPTION_KEY", "")
    cors = [x.strip().lower() for x in os.getenv("CORS_ALLOWED_ORIGINS", "").split(",") if x.strip()]
    cors_hosts = [hostname(origin) for origin in cors]

    if env not in {"staging", "production"}:
        fail("APP_ENV must be staging or production")
    if db != "mysql":
        fail("Namecheap staging/production must use MySQL; SQLite is not allowed")
    if len(session_secret) < 32 or "replace" in session_secret.lower() or "change_me" in session_secret.lower():
        fail("SESSION_SECRET must be a real random secret with at least 32 characters")
    if len(encryption_key) < 32 or "replace" in encryption_key.lower() or "change_me" in encryption_key.lower():
        fail("ENCRYPTION_KEY must be a real random secret with at least 32 characters")
    if not os.getenv("DB_HOST"):
        fail("DB_HOST is required")
    if not os.getenv("DB_USERNAME") or not os.getenv("DB_PASSWORD") or not os.getenv("DB_DATABASE"):
        fail("DB_USERNAME, DB_PASSWORD and DB_DATABASE are required")

    if env == "staging":
        if not app_host:
            fail("APP_URL must be an absolute http(s) URL for staging")
        if host_is(app_host, "elawaady.com"):
            fail("Live elawaady.com must never be used as the staging APP_URL")
        if not host_is(app_host, "e-network.net"):
            fail("Staging APP_URL must use e-network.net")
        if any(host and host_is(host, "elawaady.com") for host in cors_hosts):
            fail("Live elawaady.com must not be enabled during staging")
        if not any(host and host_is(host, "e-network.net") for host in cors_hosts):
            fail("Staging CORS must include e-network.net")

    require_backend_tree()
    require_runtime_dependencies()

    print("[OK] production/staging environment and repository checks passed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
