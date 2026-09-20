"""One bounded cell; reuse provider fetch/parser contracts, never run their bulk main()."""
import contextlib
import importlib
import json
import os
import sys
import time
from urllib.parse import urlparse


def fetch(provider, occupation, region, search_url=""):
    module = importlib.import_module("fetch_" + {
        "careerjet": "careerjet_jobs", "recruit_agent": "recruit_agent_jobs",
        "meitec_next": "meitec_next_jobs",
    }[provider])
    if provider == "careerjet":
        module.REQUEST_INTERVAL = max(1.0, module.REQUEST_INTERVAL)
        # Existing caps (20/page, 3 pages), timeout, auth, referer, interval remain intact.
        metadata = {}
        with module.requests.Session() as session:
            jobs = module.fetch_cell(session, region, occupation, metadata=metadata)
        time.sleep(max(1.0, module.REQUEST_INTERVAL))
        return {"jobs": jobs, "completed": True, "scope": "bounded_search_results", "metadata": metadata}

    host = {"recruit_agent": "www.r-agent.com", "meitec_next": "www.m-next.jp"}[provider]
    parsed = urlparse(search_url)
    if parsed.scheme != "https" or parsed.netloc != host:
        raise ValueError("InvalidSearchUrl")
    if not parsed.path.startswith("/job_search/" if provider == "recruit_agent" else "/job/s/"):
        raise ValueError("InvalidSearchPath")
    module.SEARCH_URL = search_url
    if provider == "recruit_agent":
        module.TARGET_PATH = parsed.path
        module.MAX_PAGES = 1
        module.ALLOW_QUERY_JOB_LINKS = False
        module.ALLOW_REDIRECTS = False
    else:
        module.is_search_result_url = lambda url: urlparse(url).netloc == host and urlparse(url).path.startswith(parsed.path)

    original = module.fetch_html
    fetched_pages = 0

    def guarded_fetch(url):
        nonlocal fetched_pages
        target = urlparse(url)
        if provider == "recruit_agent" and target.query:
            raise ValueError("UnapprovedRecruitQuery")
        if target.scheme != "https" or target.netloc != host:
            raise ValueError("CrossHostUrl")
        fetched_pages += 1
        if fetched_pages > 500:
            raise RuntimeError("RequestBudgetExceeded")
        # Keep provider retry/timeouts; fail the cell instead of treating failed pages as absent jobs.
        result = original(url)
        if result is None:
            raise RuntimeError("FetchFailed")
        return result

    module.fetch_html = guarded_fetch
    urls, _ = module.collect_job_urls()
    if not urls:
        # Existing parsers cannot distinguish a true empty result from a layout/access change.
        raise RuntimeError("UnconfirmedEmptyResults")
    if provider == "recruit_agent":
        urls = urls[:10]
    jobs = []
    for index, url in enumerate(urls, 1):
        time.sleep(max(1.0, module.REQUEST_INTERVAL))
        job = module.fetch_job_detail(url, index, len(urls))
        if job is None:
            raise RuntimeError("DetailParseFailed")
        jobs.append(job)
    return {"jobs": jobs, "completed": True, "scope": "bounded_search_results"}


def main():
    try:
        # Existing diagnostic prints are intentionally discarded, not included in reports/logs.
        with open(os.devnull, "w") as sink, contextlib.redirect_stdout(sink), contextlib.redirect_stderr(sink):
            payload = fetch(*sys.argv[1:])
        print(json.dumps(payload, ensure_ascii=False))
    except Exception:
        print('{"error":"FetchFailed"}', file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
