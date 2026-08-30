"""One-step database initializer for the deployed environment.
Uses production-safe seed data. Test/staging fixtures remain in seed_staging.py for automated tests only.
"""
import os
import sys

APP_ROOT = os.path.dirname(os.path.abspath(__file__))
if APP_ROOT not in sys.path:
    sys.path.insert(0, APP_ROOT)


def load_env():
    env_file = os.path.join(APP_ROOT, ".env")
    if not os.path.exists(env_file):
        return
    with open(env_file, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            key = key.strip()
            value = value.strip().strip("'\"")
            if key and key not in os.environ:
                os.environ[key] = value


load_env()

from src.Core.DatabaseFactory import DatabaseFactory
from database.migrations.seed_production import seed_production


if __name__ == "__main__":
    db = DatabaseFactory.create()
    print(f"[*] Connected using driver: {db.get_driver_name()}")
    seed_production(db)
    print("[✓] Production-safe database initialization completed.")
