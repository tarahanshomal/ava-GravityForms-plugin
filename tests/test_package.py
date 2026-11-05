"""Distribution boundaries must hold even when the checkout contains secrets."""
import importlib.util
import tempfile
import unittest
import zipfile
from pathlib import Path

spec = importlib.util.spec_from_file_location("package", Path(__file__).resolve().parents[1] / "scripts" / "package.py")
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


class PackageTest(unittest.TestCase):
    def fixture(self, root):
        (root / "ava-gravityforms-plugin.php").write_text("<?php\n/*\nVersion: 1.0.0\n*/\n")
        (root / "readme.txt").write_text("=== Ava ===\n")
        (root / "LICENSE").write_text("GPL-2.0-or-later\n")
        (root / "includes").mkdir()
        (root / "uninstall.php").write_text("<?php\n")
        (root / "index.php").write_text("<?php\n")
        for name in ("settings", "phone", "api", "delivery", "admin", "addon"):
            (root / "includes" / ("class-" + name + ".php")).write_text("<?php\n")

    def test_reproducible_zip_excludes_development_and_secret_files(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            self.fixture(root)
            (root / ".env").write_text("PRIVATE=do-not-ship")
            (root / "tests").mkdir()
            (root / "tests" / "fixture.php").write_text("<?php // development")
            (root / "includes" / "credentials.env").write_text("PRIVATE=do-not-ship")
            (root / "includes" / "credentials.json").write_text('{"PRIVATE":"do-not-ship"}')
            (root / "includes" / "debug.php").write_text("<?php // development")
            first, second = root / "first.zip", root / "second.zip"
            package.build(first, root)
            package.build(second, root)
            self.assertEqual(first.read_bytes(), second.read_bytes())
            with zipfile.ZipFile(first) as archive:
                self.assertEqual(archive.testzip(), None)
                expected = [
                    "LICENSE", "ava-gravityforms-plugin.php", "index.php", "readme.txt", "uninstall.php",
                    "includes/class-settings.php", "includes/class-phone.php", "includes/class-api.php",
                    "includes/class-delivery.php", "includes/class-admin.php", "includes/class-addon.php",
                ]
                self.assertEqual(archive.namelist(), ["ava-GravityForms-plugin/" + name for name in sorted(expected)])

    def test_refuses_symlink_in_production_tree(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            self.fixture(root)
            (root / "includes" / "class-api.php").unlink()
            (root / "includes" / "class-api.php").symlink_to(root / "ava-gravityforms-plugin.php")
            with self.assertRaises(ValueError):
                package.files(root)

    def test_refuses_symlink_directory_even_within_source(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            self.fixture(root)
            (root / "includes").rename(root / "actual-includes")
            (root / "includes").symlink_to(root / "actual-includes", target_is_directory=True)
            with self.assertRaises(ValueError):
                package.files(root)

    def test_requires_all_runtime_classes(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            self.fixture(root)
            (root / "includes" / "class-delivery.php").unlink()
            with self.assertRaises(ValueError):
                package.files(root)

    def test_requires_plugin_header(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            self.fixture(root)
            self.assertEqual(package.version(root), "1.0.0")
            (root / "ava-gravityforms-plugin.php").write_text("<?php\n")
            with self.assertRaises(ValueError):
                package.version(root)


if __name__ == "__main__":
    unittest.main()
