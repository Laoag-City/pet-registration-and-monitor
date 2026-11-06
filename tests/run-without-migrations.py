r"""Run the full suite against in-memory schema fixtures, with no migration commands.

Copies tests into a temporary directory and replaces only the existing migration
setup statement. Production files and the original tests are not rewritten.
Tests\TestCase still fails closed unless the connection is SQLite :memory:.
"""
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parent.parent
setup = "$this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);"
fixture = "\\Illuminate\\Support\\Facades\\DB::unprepared(file_get_contents(base_path('tests/Fixtures/schema.sql')));"
with tempfile.TemporaryDirectory(prefix="pawid-tests-") as directory:
    destination = Path(directory)
    for source in (root / "tests").rglob("*Test.php"):
        content = source.read_text(encoding="utf-8").replace(setup, fixture)
        if "artisan('migrate" in content or "use RefreshDatabase;" in content:
            raise RuntimeError(f"Unsupported database setup in {source}")
        target = destination / source.relative_to(root / "tests")
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding="utf-8")
    result = subprocess.run(["php", "vendor/bin/phpunit", "--configuration", "phpunit.xml", str(destination)], cwd=root)
    raise SystemExit(result.returncode)
