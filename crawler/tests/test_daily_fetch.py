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

class RecruitOnboardingWindowTest(unittest.TestCase):
    def test_first_page_and_ten_details_are_a_bounded_window(self):
        module = types.SimpleNamespace(REQUEST_INTERVAL=1.0)
        module.fetch_html = lambda url: 'html'
        module.collect_job_urls = lambda: ([f'https://www.r-agent.com/viewjob/jk{i}/' for i in range(12)], 1)
        module.fetch_job_detail = lambda url, index, total: {'external_id': str(index), 'source_url': url}
        with patch.object(daily.importlib, 'import_module', return_value=module), patch.object(daily.time, 'sleep'):
            payload = daily.fetch('recruit_agent', '機械設計', '兵庫県', 'https://www.r-agent.com/job_search/area-hyogo/kw/design/')
        self.assertEqual(module.MAX_PAGES, 1)
        self.assertEqual(len(payload['jobs']), 10)
        self.assertEqual(payload['scope'], 'bounded_search_results')

    def test_forbidden_cursor_request_is_rejected_before_http(self):
        module = types.SimpleNamespace(REQUEST_INTERVAL=1.0)
        http = unittest.mock.Mock(return_value='html')
        module.fetch_html = http
        module.collect_job_urls = lambda: module.fetch_html('https://www.r-agent.com/job_search/cell/?cursor=blocked')
        with patch.object(daily.importlib, 'import_module', return_value=module), self.assertRaisesRegex(ValueError, 'UnapprovedRecruitQuery'):
            daily.fetch('recruit_agent', '機械設計', '兵庫県', 'https://www.r-agent.com/job_search/cell/')
        http.assert_not_called()

    def test_query_links_are_not_stripped_to_bypass_robots(self):
        import fetch_recruit_agent_jobs as recruit
        html = '<a href="/viewjob/jkblocked/?jobKey=secret">blocked</a><a href="/viewjob/jkclean/">clean</a>'
        with patch.object(recruit, 'ALLOW_QUERY_JOB_LINKS', False, create=True), patch.object(recruit, 'MAX_PAGES', 1), patch.object(recruit, 'fetch_html', return_value=html):
            urls, count = recruit.collect_job_urls()
        self.assertEqual(urls, ['https://www.r-agent.com/viewjob/jkclean/'])
        self.assertEqual(count, 1)

    def test_unapproved_redirect_is_not_followed(self):
        import fetch_recruit_agent_jobs as recruit
        response = types.SimpleNamespace(is_redirect=True)
        with patch.object(recruit, 'ALLOW_REDIRECTS', False, create=True), patch.object(recruit.session, 'get', return_value=response) as get:
            self.assertIsNone(recruit.fetch_html('https://www.r-agent.com/viewjob/jkclean/'))
        self.assertFalse(get.call_args.kwargs['allow_redirects'])
