#!/usr/bin/env python3
"""Build a deterministic, installable WordPress ZIP from an explicit allowlist."""
from __future__ import annotations

import argparse
import hashlib
import re
import stat
import zipfile
from pathlib import Path

SLUG = "ava-GravityForms-plugin"
ROOT = Path(__file__).resolve().parents[1]
TOP_LEVEL = ("ava-gravityforms-plugin.php", "uninstall.php", "index.php", "readme.txt", "README.md", "LICENSE", "CHANGELOG.md")
RUNTIME_FILES = (
    "includes/class-settings.php", "includes/class-phone.php", "includes/class-api.php",
    "includes/class-delivery.php", "includes/class-admin.php", "includes/class-addon.php",
)
FIXED_TIMESTAMP = (2026, 11, 5, 0, 0, 0)


def version(root: Path = ROOT) -> str:
    main = (root / "ava-gravityforms-plugin.php").read_text(encoding="utf-8")
    match = re.search(r"^\s*\*?\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.-]+)?)\s*$", main, re.MULTILINE)
    if not match:
        raise ValueError("Main plugin file must declare a semantic Version header")
    return match.group(1)


def files(root: Path = ROOT) -> list[Path]:
    selected = []
    for name in TOP_LEVEL + RUNTIME_FILES:
        path = root / name
        if path.exists() or path.is_symlink():
            selected.append(path)
    for required in ("ava-gravityforms-plugin.php", "uninstall.php", "index.php", "readme.txt", "LICENSE") + RUNTIME_FILES:
        if root / required not in selected:
            raise ValueError("Missing required distribution file: " + required)
    for path in selected:
        relative = path.relative_to(root)
        parents = [root.joinpath(*relative.parts[:index]) for index in range(1, len(relative.parts))]
        if path.is_symlink() or any(parent.is_symlink() for parent in parents) or any(part.startswith(".") for part in relative.parts):
            raise ValueError("Refusing hidden or symlink distribution file: " + str(path))
        if not path.is_file():
            raise ValueError("Distribution path is not a file: " + str(path))
        if not path.resolve().is_relative_to(root.resolve()):
            raise ValueError("Distribution file escapes source root")
    return sorted(selected, key=lambda path: path.relative_to(root).as_posix())


def build(output: Path, root: Path = ROOT) -> Path:
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in files(root):
            name = SLUG + "/" + path.relative_to(root).as_posix()
            info = zipfile.ZipInfo(name, FIXED_TIMESTAMP)
            info.create_system = 3
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    return output


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path)
    parser.add_argument("--tag", help="Require the Git tag to match the plugin Version header")
    args = parser.parse_args()
    release_version = version()
    if args.tag and args.tag != "v" + release_version:
        parser.error("Tag must be v" + release_version)
    output = args.output or ROOT / "build" / (SLUG + "-" + release_version + ".zip")
    build(output)
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix(output.suffix + ".sha256").write_text(digest + "  " + output.name + "\n", encoding="ascii")
    print(str(output))
    print("SHA256: " + digest)


if __name__ == "__main__":
    main()
