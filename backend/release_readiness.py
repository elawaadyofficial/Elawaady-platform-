#!/usr/bin/env python3
"""Offline backend release-readiness inspector.

This script never connects to a database or network service. It only inspects the
repository tree and recovery audit metadata so CI can distinguish an expected
"source not imported yet" state from a genuinely inconsistent release candidate.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
AUDIT_PATH = ROOT / "source_recovery_audit.json"

REQUIRED_FILES = [
    ROOT / "src" / "Api" / "App.py",
    ROOT / "src" / "Api" / "WsgiAdapter.py",
    ROOT / "src" / "Core" / "DatabaseFactory.py",
]

REQUIRED_DIRS = [
    ROOT / "database",
    ROOT / "tests",
]


def main() -> int:
    audit = json.loads(AUDIT_PATH.read_text(encoding="utf-8"))
    missing_files = [str(path.relative_to(ROOT)) for path in REQUIRED_FILES if not path.is_file()]
    missing_dirs = [str(path.relative_to(ROOT)) for path in REQUIRED_DIRS if not path.is_dir()]
    import_committed = bool(audit.get("sanitized_validation", {}).get("import_committed", False))
    source_present = not missing_files and not missing_dirs

    errors: list[str] = []
    if import_committed and not source_present:
        errors.append("audit marks import_committed=true but required runtime source is incomplete")
    if source_present and not import_committed:
        errors.append("runtime source is present but audit still marks import_committed=false")

    payload = {
        "ready_for_backend_safety_gate": source_present and import_committed and not errors,
        "source_present": source_present,
        "import_committed": import_committed,
        "missing_files": missing_files,
        "missing_directories": missing_dirs,
        "errors": errors,
        "network_access": False,
        "production_access": False,
    }
    print(json.dumps(payload, indent=2, sort_keys=True))

    if errors:
        return 2
    if not payload["ready_for_backend_safety_gate"]:
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
