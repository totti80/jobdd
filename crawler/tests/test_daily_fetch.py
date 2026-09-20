import types
import unittest
from unittest.mock import patch
import fetch_daily_cell as daily


class DailyFetchTest(unittest.TestCase):
    def provider(self, failed=False):
        module = types.SimpleNamespace(REQUEST_INTERVAL=1.0)
        module.fetch_html = lambda url: None if failed else "html"
        module.collect_job_urls = lambda: (["https://www.r-agent.com/viewjob/1/"], 1)
        module.fetch_job_detail = lambda url, index, total: {"external_id": "one"}
        return module

    def test_reuses_agent_collection_detail_and_delay(self):
        module = self.provider()
        with patch.object(daily.importlib, "import_module", return_value=module), patch.object(daily.time, "sleep") as sleep:
            payload = daily.fetch("recruit_agent", "機械設計", "兵庫県", "https://www.r-agent.com/job_search/area-hyogo/kw/design/")
        self.assertEqual(payload["jobs"], [{"external_id": "one"}])
        self.assertEqual(module.TARGET_PATH, "/job_search/area-hyogo/kw/design/")
        sleep.assert_called_once_with(1.0)

    def test_never_treats_unknown_empty_or_failed_details_as_success(self):
        for urls, detail in [([], {}), (["https://www.r-agent.com/viewjob/1/"], None)]:
            module = self.provider()
            module.collect_job_urls = lambda: (urls, 1)
            module.fetch_job_detail = lambda *args: detail
            with patch.object(daily.importlib, "import_module", return_value=module), patch.object(daily.time, "sleep"), self.assertRaises(RuntimeError):
                daily.fetch("recruit_agent", "機械設計", "兵庫県", "https://www.r-agent.com/job_search/cell/")

    def test_rejects_wrong_host_and_propagates_old_fetch_failures(self):
        module = self.provider(failed=True)
        with patch.object(daily.importlib, "import_module", return_value=module), self.assertRaises(ValueError):
            daily.fetch("recruit_agent", "機械設計", "兵庫県", "https://evil.test/job_search/")
        module.collect_job_urls = lambda: module.fetch_html("https://www.r-agent.com/job_search/cell/")
        with patch.object(daily.importlib, "import_module", return_value=module), self.assertRaises(RuntimeError):
            daily.fetch("recruit_agent", "機械設計", "兵庫県", "https://www.r-agent.com/job_search/cell/")

class CareerjetResponseTest(unittest.TestCase):
    def test_error_payload_is_not_an_empty_successful_cell(self):
        import fetch_careerjet_jobs as careerjet
        response = types.SimpleNamespace(raise_for_status=lambda: None, json=lambda: {"error": "access denied"})
        session = types.SimpleNamespace(get=lambda *args, **kwargs: response)
        with patch.dict("os.environ", {"CAREERJET_API_KEY": "test-key"}), self.assertRaises(ValueError):
            careerjet.fetch_cell(session, "兵庫県", "機械設計")
