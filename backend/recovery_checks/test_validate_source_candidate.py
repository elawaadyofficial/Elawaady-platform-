#!/usr/bin/env python3
"""Regression checks for backend source candidate admission.

Runs entirely offline with temporary fixtures. It does not connect to a database,
network endpoint, deployment target, or the live store.
"""

from __future__ import annotations

import importlib.util
import tempfile
from pathlib import Path

BACKEND = Path(__file__).resolve().parents[1]
VALIDATOR = BACKEND / "validate_source_candidate.py"

spec = importlib.util.spec_from_file_location("validate_source_candidate", VALIDATOR)
assert spec and spec.loader
validator = importlib.util.module_from_spec(spec)
spec.loader.exec_module(validator)


def write_minimal_candidate(root: Path) -> None:
    files = {
        "src/Api/App.py": "def app():\n    return 'ok'\n",
        "src/Api/WsgiAdapter.py": "def adapt(app):\n    return app\n",
        "src/Core/DatabaseFactory.py": "class DatabaseFactory:\n    pass\n",
        "database/schema.sql": "CREATE TABLE example (id INTEGER PRIMARY KEY);\n",
        "tests/test_smoke.py": "def test_smoke():\n    assert True\n",
    }
    for rel, text in files.items():
        path = root / rel
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text, encoding="utf-8")


def assert_rejected(mutator, expected_fragment: str) -> None:
    policy = validator.load_policy()
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)
        write_minimal_candidate(root)
        mutator(root)
        errors = validator.validate_candidate(root, policy)
        assert any(expected_fragment in error for error in errors), errors


def main() -> int:
    policy = validator.load_policy()
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)
        write_minimal_candidate(root)
        assert validator.validate_candidate(root, policy) == []

    assert_rejected(
        lambda root: (root / ".env").write_text("APP_ENV=staging\n", encoding="utf-8"),
        "forbidden sensitive/database file: .env",
    )
    assert_rejected(
        lambda root: (root / "database" / "snapshot.sqlite").write_bytes(b"SQLite format 3\\x00"),
        "forbidden sensitive/database file: database/snapshot.sqlite",
    )
    assert_rejected(
        lambda root: (root / "src" / "Api" / "App.py").write_text(
            "API_KEY='hardcoded-secret-value'\n", encoding="utf-8"
        ),
        "fixed_secret_assignment found in src/Api/App.py",
    )
    assert_rejected(
        lambda root: (root / "src" / "Api" / "App.py").write_text(
            "BASE='https://elawaady.com/api'\n", encoding="utf-8"
        ),
        "live_store_url found in src/Api/App.py",
    )
    assert_rejected(
        lambda root: (root / "src" / "Api" / "App.py").unlink(),
        "required runtime path missing: src/Api/App.py",
    )
    assert_rejected(
        lambda root: (
            (root / "src" / "Api" / "WsgiAdapter.py").unlink(),
            (root / "src" / "WsgiAdapter.py").write_text("def adapt(app):\n    return app\n", encoding="utf-8"),
        ),
        "required runtime path missing: src/Api/WsgiAdapter.py",
    )
    assert_rejected(
        lambda root: (root / "README.tmp").write_text("unexpected\n", encoding="utf-8"),
        "path outside allowlist: README.tmp",
    )

    print("backend source candidate validator regression checks: OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
