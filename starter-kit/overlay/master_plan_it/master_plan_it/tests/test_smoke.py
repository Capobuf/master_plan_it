from pathlib import Path


def test_canonical_metadata_path_exists():
    repo_root = Path(__file__).resolve().parents[5]
    canonical = repo_root / "apps/master_plan_it/master_plan_it/master_plan_it"
    assert canonical.exists(), f"Canonical metadata path missing: {canonical}"
