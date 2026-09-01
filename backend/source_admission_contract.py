"""Guardrails for admitting recovered backend source into the repository.

This check is intentionally offline and deployment-free. It allows the historical
`source_bundle/` quarantine to remain for audit purposes, but rejects risky
recovery artifacts anywhere else under `backend/`.
"""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent
AUDIT_PATH = ROOT / "source_recovery_audit.json"
QUARANTINE = ROOT / "source_bundle"

REQUIRED_ADMITTED_PATHS = (
    ROOT / "src/Api/App.py",
    ROOT / "src/Api/WsgiAdapter.py",
    ROOT / "src/Core/DatabaseFactory.py",
    ROOT / "database",
    ROOT / "tests",
)

FORBIDDEN_SUFFIXES = {".sqlite", ".sqlite3", ".db", ".dump", ".bak"}
FORBIDDEN_NAMES = {".env", "database.sql", "backup.sql", "dump.sql"}
TEXT_SUFFIXES = {".py", ".json", ".yml", ".yaml", ".md", ".txt", ".sql", ".ini", ".toml"}

# Recovery-source findings that must not re-enter the reviewed source tree.
FORBIDDEN_TEXT_PATTERNS = (
    re.compile(r"/working_dir(?:/|\\b)"),
    re.compile(r"(?i)bootstrap_(?:password|secret)\s*=\s*['\"][^'\"]+['\"]"),
)


def in_quarantine(path: Path) -> bool:
    try:
        path.relative_to(QUARANTINE)
        return True
    except ValueError:
        return False


def scan_tree() -> list[str]:
    problems: list[str] = []
    for path in ROOT.rglob("*"):
        if not path.is_file() or in_quarantine(path):
            continue

        relative = path.relative_to(ROOT).as_posix()
        lower_name = path.name.lower()
        if lower_name in FORBIDDEN_NAMES or path.suffix.lower() in FORBIDDEN_SUFFIXES:
            problems.append(f"forbidden recovery artifact: {relative}")
            continue

        if path.suffix.lower() not in TEXT_SUFFIXES:
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            problems.append(f"unexpected non-UTF-8 text candidate: {relative}")
            continue

        for pattern in FORBIDDEN_TEXT_PATTERNS:
            if pattern.search(text):
                problems.append(f"unsafe recovery-source pattern in {relative}: {pattern.pattern}")

    return problems


def validate_admission_state() -> list[str]:
    problems: list[str] = []
    audit = json.loads(AUDIT_PATH.read_text(encoding="utf-8"))
    source_present = any((ROOT / "src").glob("**/*.py")) if (ROOT / "src").exists() else False
    import_committed = bool(audit.get("sanitized_validation", {}).get("import_committed"))

    if source_present:
        missing = [p.relative_to(ROOT).as_posix() for p in REQUIRED_ADMITTED_PATHS if not p.exists()]
        if missing:
            problems.append("admitted backend source is incomplete: " + ", ".join(missing))
        if not import_committed:
            problems.append("backend/src exists but source_recovery_audit.json does not mark import_committed=true")
        if audit.get("raw_source_import_allowed") is not False:
            problems.append("raw_source_import_allowed must remain false after sanitized admission")
    elif import_committed:
        problems.append("audit marks import_committed=true but backend/src is absent")

    return problems


def main() -> int:
    problems = scan_tree() + validate_admission_state()
    if problems:
        for problem in problems:
            print(f"[FAIL] {problem}")
        return 1
    print("[OK] backend recovery quarantine/admission contract passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
