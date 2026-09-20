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
    def test_fetch_cell_paginates_without_copying_search_region(self):
        with patch.object(MODULE, "PAGE_SIZE", 1), patch.object(MODULE, "MAX_PAGES", 2), patch.object(MODULE, "REQUEST_INTERVAL", 0), patch.dict("os.environ", {"CAREERJET_API_KEY": "test-key"}):
            jobs = MODULE.fetch_cell(FakeSession(), "兵庫県", "機械設計")

        self.assertEqual(len(jobs), 2)
        self.assertTrue(all(job["region"] is None for job in jobs))
        self.assertTrue(all(job["search_region"] == "兵庫県" for job in jobs))
        self.assertTrue(all(job["search_occupation"] == "機械設計" for job in jobs))

    def test_repeated_page_with_changed_tracking_urls_is_rejected(self):
        session = FakeSession()
        session.pages = [FakeResponse([{"title": "same", "url": "https://track.test/a"}]),
                         FakeResponse([{"title": "same", "url": "https://track.test/b"}])]
        with patch.object(MODULE, "PAGE_SIZE", 1), patch.object(MODULE, "MAX_PAGES", 2), patch.object(MODULE, "REQUEST_INTERVAL", 0), patch.dict("os.environ", {"CAREERJET_API_KEY": "test-key"}), self.assertRaisesRegex(ValueError, "RepeatedCareerjetPage"):
            MODULE.fetch_cell(session, "兵庫県", "機械設計")

    def test_provider_locations_are_preserved_separately_from_query_context(self):
        session = FakeSession()
        session.pages = [FakeResponse([{"title": "design", "locations": "大阪府大阪市"}])]
        with patch.dict("os.environ", {"CAREERJET_API_KEY": "test-key"}):
            jobs = MODULE.fetch_cell(session, "兵庫県", "機械設計")
        self.assertEqual(jobs[0]["region"], "大阪府大阪市")
        self.assertEqual(jobs[0]["search_region"], "兵庫県")
