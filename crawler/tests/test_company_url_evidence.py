import unittest
from unittest.mock import patch
from company_url_evidence import extract_company_url_evidence
import fetch_recruit_agent_jobs as recruit
import fetch_meitec_next_jobs as meitec
import fetch_careerjet_jobs as careerjet


class CompanyUrlEvidenceTest(unittest.TestCase):
    def test_normalizers_preserve_company_and_sameas(self):
        data = {"hiringOrganization": {"name": "企業A", "url": "https://a.example/about",
                                     "sameAs": ["https://a.example/", "https://a.example/"]}}
        for row in (recruit.normalize_jobposting(data, "https://source.example/job", {"salary_min": None, "salary_max": None}),
                    meitec.normalize_jobposting(data, "https://source.example/job")):
            evidence = row["company_url_evidence"]
            self.assertEqual(evidence[0]["raw_value"], "https://a.example/about")
            self.assertTrue(any("sameAs" in e["raw_field"] for e in evidence))
            self.assertTrue(all(e["fetched_at"] and e["review_status"] == "needs_review" for e in evidence))

    def test_no_url_no_guess_and_publisher_is_not_employer(self):
        for data in ({}, {"hiringOrganization": {"name": "企業A"}},
                     {"organization": {"name": "媒体B", "url": "https://media.example/"}}):
            self.assertEqual(extract_company_url_evidence(data, "企業A", "https://source.example/", "test"), [])

    def test_explicit_html_link_and_organization(self):
        html = '<a href="https://a.example/company/deep">企業公式サイト</a><a href="https://b.example/">別リンク</a>'
        evidence = extract_company_url_evidence({}, "企業A", "https://source.example/job", "test", html)
        self.assertEqual(len(evidence), 1)
        self.assertEqual(evidence[0]["raw_value"], "https://a.example/company/deep")
        self.assertNotIn("html", evidence[0])
        data = {"organization": {"name": "企業A", "url": "https://a.example/"}}
        self.assertEqual(len(extract_company_url_evidence(data, "企業A", "https://source.example/", "test")), 1)

    def test_careerjet_response_fields_retained(self):
        response = unittest.mock.Mock()
        response.json.return_value = {"jobs": [{"company": "企業A", "url": "https://source.example/job",
                                                "company_url": "https://a.example/"}]}
        session = unittest.mock.Mock()
        session.get.return_value = response
        with patch.dict("os.environ", {"CAREERJET_API_KEY": "fake"}):
            row = careerjet.fetch_cell(session, "大阪府", "機械設計")[0]
        self.assertEqual(row["company_url"], row["company_url_evidence"][0]["raw_value"])

    def test_detail_html_is_preserved_by_both_crawlers(self):
        html = '<script type="application/ld+json">{"@type":"JobPosting","hiringOrganization":{"name":"企業A"}}</script><a href="https://a.example/">企業公式サイト</a>'
        for module in (recruit, meitec):
            with patch.object(module, "fetch_html", return_value=html):
                row = module.fetch_job_detail("https://source.example/job", 1, 1)
            self.assertEqual(row["company_url_evidence"][0]["raw_value"], "https://a.example/")
