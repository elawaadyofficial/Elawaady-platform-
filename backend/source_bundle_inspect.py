#!/usr/bin/env python3
"""Read-only inspection for the historical backend recovery payload.

The repository contains only chunk 1 of an originally planned 8-part bundle.
This inspector fails with an explicit completeness diagnostic before attempting
to decompress an incomplete gzip stream. It never extracts or modifies files.
"""
from __future__ import annotations

import argparse
import base64
import gzip
import hashlib
import io
import json
import sys
import tarfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
DEFAULT_BUNDLE = ROOT / "source_bundle" / "part01.b64"


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def expected_parts(bundle_path: Path, explicit: int | None) -> int:
    if explicit is not None:
        return explicit
    manifest_path = bundle_path.parent / "manifest.json"
    if manifest_path.is_file():
        try:
            value = int(json.loads(manifest_path.read_text(encoding="utf-8"))["expected_parts"])
        except (KeyError, TypeError, ValueError, json.JSONDecodeError) as exc:
            print(f"[FAIL] Invalid recovery manifest: {exc}", file=sys.stderr)
            return -1
        return value
    return 1


def read_bundle(bundle_path: Path, part_count: int) -> bytes | None:
    if part_count < 1:
        return None
    if part_count == 1:
        if not bundle_path.is_file():
            print(f"[FAIL] Bundle not found: {bundle_path}", file=sys.stderr)
            return None
        return bundle_path.read_bytes().strip()

    directory = bundle_path.parent
    paths = [directory / f"part{i:02d}.b64" for i in range(1, part_count + 1)]
    missing = [path.name for path in paths if not path.is_file()]
    if missing:
        present = part_count - len(missing)
        print(
            f"[FAIL] Recovery bundle is incomplete: found {present}/{part_count} chunks; "
            f"missing {', '.join(missing)}",
            file=sys.stderr,
        )
        print("result: historical recovery payload is reference-only until every chunk is recovered", file=sys.stderr)
        return None
    return b"".join(path.read_bytes().strip() for path in paths)


def inspect(bundle_path: Path, expected: list[str], explicit_parts: int | None) -> int:
    part_count = expected_parts(bundle_path, explicit_parts)
    if part_count < 1:
        return 1
    encoded = read_bundle(bundle_path, part_count)
    if encoded is None:
        return 1

    try:
        compressed = base64.b64decode(encoded, validate=True)
    except Exception as exc:
        print(f"[FAIL] Bundle is not valid strict base64: {exc}", file=sys.stderr)
        return 1

    try:
        payload = gzip.decompress(compressed)
    except Exception as exc:
        print(f"[FAIL] Bundle is not a valid complete gzip payload: {exc}", file=sys.stderr)
        return 1

    print("EXD backend source bundle inspection")
    print(f"bundle: {bundle_path.relative_to(ROOT)}")
    print(f"parts: {part_count}")
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
    parser.add_argument("--parts", type=int, default=None, help="Expected number of partNN.b64 chunks")
    parser.add_argument("--expect", action="append", default=[], help="Expected archive path (repeatable)")
    args = parser.parse_args()
    return inspect(args.bundle.resolve(), args.expect, args.parts)


if __name__ == "__main__":
    raise SystemExit(main())
