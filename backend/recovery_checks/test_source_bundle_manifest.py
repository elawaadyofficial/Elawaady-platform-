#!/usr/bin/env python3
"""Regression checks for backend recovery bundle manifest consistency."""
from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

import sys

BACKEND_DIR = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(BACKEND_DIR))

import source_bundle_inspect as inspector  # noqa: E402


class SourceBundleManifestTests(unittest.TestCase):
    def make_bundle(self, manifest: dict, actual_parts: list[str]) -> Path:
        root = Path(tempfile.mkdtemp(prefix="exd-bundle-manifest-"))
        for name in actual_parts:
            (root / name).write_text("QQ==\n", encoding="utf-8")
        (root / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")
        return root / "part01.b64"

    def base_manifest(self) -> dict:
        return {
            "schema_version": 1,
            "state": "incomplete_reference_only",
            "expected_parts": 2,
            "present_parts": ["part01.b64"],
            "missing_parts": ["part02.b64"],
            "origin_commit": "a" * 40,
            "origin_commit_message": "reference bundle",
            "reference_only": True,
        }

    def test_accepts_consistent_incomplete_reference_manifest(self) -> None:
        bundle = self.make_bundle(self.base_manifest(), ["part01.b64"])
        manifest = inspector.validate_manifest(bundle)
        self.assertIsInstance(manifest, dict)
        self.assertEqual(manifest["expected_parts"], 2)

    def test_rejects_manifest_claiming_missing_file_is_present(self) -> None:
        manifest = self.base_manifest()
        manifest["present_parts"] = ["part01.b64", "part02.b64"]
        manifest["missing_parts"] = []
        manifest["state"] = "complete"
        bundle = self.make_bundle(manifest, ["part01.b64"])
        self.assertEqual(inspector.validate_manifest(bundle), {})

    def test_rejects_unlisted_part_on_disk(self) -> None:
        bundle = self.make_bundle(self.base_manifest(), ["part01.b64", "part02.b64"])
        self.assertEqual(inspector.validate_manifest(bundle), {})

    def test_rejects_incomplete_bundle_not_marked_reference_only(self) -> None:
        manifest = self.base_manifest()
        manifest["reference_only"] = False
        bundle = self.make_bundle(manifest, ["part01.b64"])
        self.assertEqual(inspector.validate_manifest(bundle), {})

    def test_rejects_inventory_outside_expected_range(self) -> None:
        manifest = self.base_manifest()
        manifest["missing_parts"] = ["part02.b64", "part03.b64"]
        bundle = self.make_bundle(manifest, ["part01.b64"])
        self.assertEqual(inspector.validate_manifest(bundle), {})

    def test_rejects_short_origin_commit(self) -> None:
        manifest = self.base_manifest()
        manifest["origin_commit"] = "abc123"
        bundle = self.make_bundle(manifest, ["part01.b64"])
        self.assertEqual(inspector.validate_manifest(bundle), {})


if __name__ == "__main__":
    unittest.main()
