#!/usr/bin/env python3
"""Read-only inspection for the bundled backend recovery payload.

This tool never extracts files and never writes to the repository. It validates
that backend/source_bundle/part01.b64 is strict base64, decompresses the gzip
payload in memory, reports hashes/sizes, and (when the payload is a tar archive)
lists members so the missing backend source tree can be verified before any
recovery attempt.
"""

from __future__ import annotations

import argparse
import base64
import gzip
import hashlib
import io
import sys
import tarfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
DEFAULT_BUNDLE = ROOT / "source_bundle" / "part01.b64"


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def inspect(bundle_path: Path, expected: list[str]) -> int:
    if not bundle_path.is_file():
        print(f"[FAIL] Bundle not found: {bundle_path}", file=sys.stderr)
        return 1

    encoded = bundle_path.read_bytes().strip()
    try:
        compressed = base64.b64decode(encoded, validate=True)
    except Exception as exc:
        print(f"[FAIL] Bundle is not valid strict base64: {exc}", file=sys.stderr)
        return 1

    try:
        payload = gzip.decompress(compressed)
    except Exception as exc:
        print(f"[FAIL] Bundle is not a valid gzip payload: {exc}", file=sys.stderr)
        return 1

    print("EXD backend source bundle inspection")
    print(f"bundle: {bundle_path.relative_to(ROOT)}")
    print(f"base64_bytes: {len(encoded)}")
    print(f"gzip_bytes: {len(compressed)}")
    print(f"payload_bytes: {len(payload)}")
    print(f"gzip_sha256: {sha256(compressed)}")
    print(f"payload_sha256: {sha256(payload)}")

    try:
        with tarfile.open(fileobj=io.BytesIO(payload), mode="r:*") as archive:
            members = [m.name for m in archive.getmembers() if m.isfile()]
    except tarfile.TarError:
        print("format: gzip (non-tar payload)")
        print("result: valid compressed payload; manual format review required")
        return 0

    print("format: tar.gz")
    print(f"files: {len(members)}")
    for name in members:
        print(f" - {name}")

    if expected:
        normalized = {name.lstrip("./") for name in members}
        missing = [item for item in expected if item.lstrip("./") not in normalized]
        if missing:
            print("[FAIL] Expected recovery files are missing from archive:", file=sys.stderr)
            for item in missing:
                print(f" - {item}", file=sys.stderr)
            return 1
        print("expected_files: present")

    print("result: bundle is readable; no files were extracted or modified")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--bundle", type=Path, default=DEFAULT_BUNDLE)
    parser.add_argument("--expect", action="append", default=[], help="Expected archive path (repeatable)")
    args = parser.parse_args()
    return inspect(args.bundle.resolve(), args.expect)


if __name__ == "__main__":
    raise SystemExit(main())
