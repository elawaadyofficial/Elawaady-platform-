"""cPanel Phusion Passenger WSGI startup for Elawaady XDigital backend."""
import os
import sys

APP_ROOT = os.path.dirname(os.path.abspath(__file__))
if APP_ROOT not in sys.path:
    sys.path.insert(0, APP_ROOT)

for venv_path in [
    os.path.join(APP_ROOT, "..", "virtualenv", "e-network-backend", "3.10", "lib", f"python{sys.version_info.major}.{sys.version_info.minor}", "site-packages"),
    os.path.join(APP_ROOT, "venv", "lib", f"python{sys.version_info.major}.{sys.version_info.minor}", "site-packages"),
    os.path.join(APP_ROOT, ".venv", "lib", f"python{sys.version_info.major}.{sys.version_info.minor}", "site-packages"),
]:
    venv_path = os.path.abspath(venv_path)
    if os.path.exists(venv_path) and venv_path not in sys.path:
        sys.path.insert(0, venv_path)


def _load_env_file(file_path):
    if not os.path.isfile(file_path):
        return
    with open(file_path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            key = key.strip()
            value = value.strip().strip("'\"")
            if key and key not in os.environ:
                os.environ[key] = value


_load_env_file(os.path.join(APP_ROOT, ".env"))

from src.Api.App import Application
from src.Api.WsgiAdapter import WsgiAdapter
from src.Core.DatabaseFactory import DatabaseFactory

db = DatabaseFactory.create()
application_instance = Application(db=db)
application = WsgiAdapter(application_instance)
app = application
