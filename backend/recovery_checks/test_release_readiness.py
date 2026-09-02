#!/usr/bin/env python3
"""Regression checks for the offline backend release-readiness inspector."""

from __future__ import annotations

import importlib.util
import json
import tempfile
from pathlib import Path

MODULE_PATH = Path(__file__).resolve().parents[1] / "release_readiness.py"
spec = importlib.util.spec_from_file_location("release_readiness", MODULE_PATH)
assert spec and spec.loader
release_readiness = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release_readiness)


def configure(root: Path, import_committed: bool, source_present: bool) -> None:
    release_readiness.ROOT = root
    release_readiness.AUDIT_PATH = root / "source_recovery_audit.json"
    release_readiness.REQUIRED_FILES = [
        root / "src" / "Api" / "App.py",
        root / "src" / "Api" / "WsgiAdapter.py",
        root / "src" / "Core" / "DatabaseFactory.py",
    ]
    release_readiness.REQUIRED_DIRS = [root / "database", root / "tests"]

    release_readiness.AUDIT_PATH.write_text(
        json.dumps({"sanitized_validation": {"import_committed": import_committed}}),
        encoding="utf-8",
    )

    if source_present:
        for path in release_readiness.REQUIRED_FILES:
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("# fixture\n", encoding="utf-8")
        for path in release_readiness.REQUIRED_DIRS:
            path.mkdir(parents=True, exist_ok=True)


def run_case(import_committed: bool, source_present: bool, expected_status: int) -> None:
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)
        configure(root, import_committed, source_present)
        actual = release_readiness.main()
        assert actual == expected_status, (
            f"import_committed={import_committed}, source_present={source_present}: "
            f"expected {expected_status}, got {actual}"
        )


def main() -> None:
    # Expected recovery state: no source yet and audit remains false.
    run_case(import_committed=False, source_present=False, expected_status=1)

    # Both inconsistent states must fail closed.
    run_case(import_committed=True, source_present=False, expected_status=2)
    run_case(import_committed=False, source_present=True, expected_status=2)

    # Only a complete admitted source plus matching audit is release-ready.
    run_case(import_committed=True, source_present=True, expected_status=0)

    print("release_readiness regression checks passed")


if __name__ == "__main__":
    main()
