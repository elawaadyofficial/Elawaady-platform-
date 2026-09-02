#!/usr/bin/env python3
"""Offline validator for a sanitized backend source candidate.

This utility never connects to a network, database, deployment target, or live store.
It validates either the admission policy itself (--policy-only) or a local candidate
staging directory before files are copied into backend/.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parent
POLICY_PATH = ROOT / "source_sanitization_policy.json"

FORBIDDEN_SUFFIXES = {
    ".db", ".sqlite", ".sqlite3", ".dump", ".bak", ".backup", ".pem", ".key", ".p12", ".pfx"
}
FORBIDDEN_BASENAMES = {".env", "id_rsa", "id_ed25519"}
FORBIDDEN_TEXT_PATTERNS = {
    "legacy_workspace_path": re.compile(r"/working_dir(?:/|\\b)"),
    "live_store_url": re.compile(r"https?://(?:www\\.)?elawaady\\.com(?:/|\\b)", re.I),
    "fixed_password_assignment": re.compile(r"(?i)(?:password|passwd|pwd)\\s*=\\s*['\"][^'\"]{6,}['\"]"),
    "fixed_secret_assignment": re.compile(r"(?i)(?:secret|api[_-]?key|token)\\s*=\\s*['\"][^'\"]{8,}['\"]"),
}
REQUIRED_RUNTIME_PATHS = {
    "src/Api/App.py",
    "src/WsgiAdapter.py",
    "src/Core/DatabaseFactory.py",
    "database/schema.sql",
    "tests",
}


def load_policy() -> dict:
    policy = json.loads(POLICY_PATH.read_text(encoding="utf-8"))
    assert policy["production_deploy_allowed"] is False
    assert policy["raw_source_import_allowed"] is False
    assert len(policy["reviewed_archive_sha256"]) == 64
    assert policy["required_validation"]["pytest_expected_passed"] == 54
    assert policy["required_validation"]["python_compileall"] is True
    assert policy["required_validation"]["production_preflight"] is True
    assert policy["required_validation"]["source_admission_contract"] is True
    return policy


def normalize_rel(path: Path, base: Path) -> str:
    return path.relative_to(base).as_posix()


def allowed_by_policy(rel: str, allowed_roots: list[str]) -> bool:
    rel_path = PurePosixPath(rel)
    for root in allowed_roots:
        root = root.rstrip("/")
        root_path = PurePosixPath(root)
        if rel_path == root_path or root_path in rel_path.parents:
            return True
    return False


def validate_candidate(candidate: Path, policy: dict) -> list[str]:
    errors: list[str] = []
    candidate = candidate.resolve()
    if not candidate.is_dir():
        return [f"candidate directory does not exist: {candidate}"]

    allowed_roots = list(policy["allowed_import_roots"])
    excluded = [item.rstrip("/") for item in policy["excluded_paths"]]
    seen: set[str] = set()

    for path in candidate.rglob("*"):
        rel = normalize_rel(path, candidate)
        if path.is_symlink():
            errors.append(f"symlink not allowed: {rel}")
            continue
        if path.is_dir():
            continue

        seen.add(rel)
        pure = PurePosixPath(rel)
        if not allowed_by_policy(rel, allowed_roots):
            errors.append(f"path outside allowlist: {rel}")
        if any(rel == item or PurePosixPath(item) in pure.parents for item in excluded):
            errors.append(f"explicitly excluded path present: {rel}")
        if path.name in FORBIDDEN_BASENAMES or path.suffix.lower() in FORBIDDEN_SUFFIXES:
            errors.append(f"forbidden sensitive/database file: {rel}")

        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            errors.append(f"non-UTF-8 payload requires manual review: {rel}")
            continue

        for name, pattern in FORBIDDEN_TEXT_PATTERNS.items():
            if pattern.search(text):
                errors.append(f"{name} found in {rel}")

    for required in sorted(REQUIRED_RUNTIME_PATHS):
        target = candidate / required
        if not target.exists():
            errors.append(f"required runtime path missing: {required}")

    env_file = candidate / ".env"
    if env_file.exists():
        errors.append("real .env file is never admissible")

    return sorted(set(errors))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("candidate", nargs="?", help="sanitized candidate directory to inspect")
    parser.add_argument("--policy-only", action="store_true", help="validate policy invariants only")
    args = parser.parse_args()

    try:
        policy = load_policy()
    except (AssertionError, KeyError, json.JSONDecodeError) as exc:
        print(f"policy validation failed: {exc}", file=sys.stderr)
        return 2

    if args.policy_only:
        print("backend source sanitization policy: OK")
        return 0

    if not args.candidate:
        parser.error("candidate is required unless --policy-only is used")

    errors = validate_candidate(Path(args.candidate), policy)
    if errors:
        print("backend source candidate rejected:", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1

    print("backend source candidate passed static admission checks")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
