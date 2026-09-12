from pathlib import Path
import importlib.util
import unittest
from unittest.mock import patch


MODULE_PATH = Path(__file__).resolve().parents[1] / "fetch_careerjet_jobs.py"
SPEC = importlib.util.spec_from_file_location("fetch_careerjet_jobs", MODULE_PATH)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(MODULE)


class FakeResponse:
    def __init__(self, jobs):
        self._jobs = jobs

    def raise_for_status(self):
        return None

    def json(self):
        return {"jobs": self._jobs}


class FakeSession:
    def __init__(self):
        self.pages = [
            FakeResponse([{"title": "機械設計1"}]),
            FakeResponse([{"title": "機械設計2"}]),
        ]

    def get(self, *args, **kwargs):
        return self.pages.pop(0)


class CareerjetFetchTest(unittest.TestCase):
    def test_fetch_cell_paginates_and_keeps_requested_cell(self):
        with patch.object(MODULE, "PAGE_SIZE", 1), patch.object(MODULE, "MAX_PAGES", 2), patch.object(MODULE, "REQUEST_INTERVAL", 0), patch.dict("os.environ", {"CAREERJET_API_KEY": "test-key"}):
            jobs = MODULE.fetch_cell(FakeSession(), "兵庫県", "機械設計")

        self.assertEqual(len(jobs), 2)
        self.assertTrue(all(job["region"] == "兵庫県" for job in jobs))
        self.assertTrue(all(job["search_occupation"] == "機械設計" for job in jobs))