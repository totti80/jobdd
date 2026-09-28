import sys
import unittest
from unittest.mock import patch
from pathlib import Path
from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from eyecatch import extract_eyecatch


class EyecatchTest(unittest.TestCase):
    def extract(self, html):
        return extract_eyecatch(BeautifulSoup(html, 'html.parser'), 'https://jobs.sample.jp/job/123/')

    def test_og_precedes_structured_and_resolves_relative(self):
        self.assertEqual(self.extract('<meta property="og:image" content="/hero.jpg"><script type="application/ld+json">{"@type":"JobPosting","image":"https://img.sample.jp/second.jpg"}</script>'), 'https://jobs.sample.jp/hero.jpg')

    def test_structured_graph_image_object(self):
        self.assertEqual(self.extract('<script type="application/ld+json">{"@graph":[{"@type":"Organization","image":"/logo.jpg"},{"@type":["WebPage"],"image":{"contentUrl":"https://img.sample.jp/main.jpg"}}]}</script>'), 'https://img.sample.jp/main.jpg')

    def test_none_and_malformed(self):
        for html in ('', '<img src="/body.jpg">', '<script type="application/ld+json">oops</script>', '<script type="application/ld+json">{"@type":42,"image":"/bad.jpg"}</script>'):
            self.assertIsNone(self.extract(html))

    def test_unsafe_or_malformed_urls(self):
        for value in ('javascript:alert(1)', 'data:image/png,abc', 'file:///tmp/pic', 'http://', 'https://bad host/a', 'https://user:pass@host.jp/a', 'https://[bad/a', 'https://host.jp:bad/a'):
            self.assertIsNone(self.extract(f'<meta property="og:image" content="{value}">'), value)

    def test_existing_meitec_flow_keeps_job_on_optional_image_failure(self):
        import fetch_meitec_next_jobs as meitec
        html = '<meta property="og:image" content="/hero.jpg"><script type="application/ld+json">{"@type":"JobPosting","title":"機械設計","hiringOrganization":{"name":"検証会社"}}</script>'
        with patch.object(meitec, 'fetch_html', return_value=html), patch.dict('os.environ', {'JOBDD_EYECATCH_APPROVED_PROVIDERS': 'meitec_next'}):
            job = meitec.fetch_job_detail('https://www.m-next.jp/job/123/', 1, 1)
            self.assertEqual(job['eyecatch_image_url'], 'https://www.m-next.jp/hero.jpg')
            with patch.object(meitec, 'extract_eyecatch', side_effect=ValueError('malformed optional metadata')):
                job = meitec.fetch_job_detail('https://www.m-next.jp/job/123/', 1, 1)
                self.assertEqual(job['title'], '機械設計')
                self.assertIsNone(job['eyecatch_image_url'])
        with patch.object(meitec, 'fetch_html', return_value=html), patch.dict('os.environ', {'JOBDD_EYECATCH_APPROVED_PROVIDERS': ''}):
            self.assertIsNone(meitec.fetch_job_detail('https://www.m-next.jp/job/123/', 1, 1)['eyecatch_image_url'])


if __name__ == '__main__':
    unittest.main()
