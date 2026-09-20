import json
import re
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin, urlparse, parse_qs

import requests
from company_url_evidence import extract_company_url_evidence
from bs4 import BeautifulSoup


# ============================================================
# 基本設定
# ============================================================

BASE_URL = "https://www.r-agent.com"

SEARCH_URL = (
    "https://www.r-agent.com/"
    "job_search/area-hyogo/"
    "kw/%E6%A9%9F%E6%A2%B0%E8%A8%AD%E8%A8%88/"
)

TARGET_PATH = (
    "/job_search/area-hyogo/"
    "kw/%E6%A9%9F%E6%A2%B0%E8%A8%AD%E8%A8%88/"
)

HEADERS = {
    "User-Agent": "Mozilla/5.0"
}

REQUEST_INTERVAL = 1.0
MAX_RETRIES = 3
MAX_PAGES = 30


# ============================================================
# 保存先
# ============================================================

project_root = Path(__file__).resolve().parent.parent

output_path = (
    project_root
    / "storage/app/private/crawler/recruit_agent_jobs.json"
)


# ============================================================
# HTTP
# ============================================================

session = requests.Session()
session.headers.update(HEADERS)


def fetch_html(url):
    for attempt in range(1, MAX_RETRIES + 1):

        try:
            response = session.get(
                url,
                timeout=20,
                allow_redirects=globals().get("ALLOW_REDIRECTS", True),
            )

            if response.is_redirect:
                return None
            response.raise_for_status()

            return response.text

        except requests.RequestException as e:
            print(
                f"request error "
                f"attempt={attempt}/{MAX_RETRIES}: "
                f"{url}"
            )
            print(e)

            if attempt < MAX_RETRIES:
                time.sleep(3)

    return None


# ============================================================
# 求人URL正規化
# ============================================================

def normalize_job_url(url):
    parsed = urlparse(url)

    return parsed._replace(
        query="",
        fragment=""
    ).geturl()


# ============================================================
# 一覧ページ巡回
# ============================================================

def collect_job_urls():

    current_url = SEARCH_URL

    visited_pages = set()
    all_jobs = set()

    page_count = 0

    while current_url and page_count < MAX_PAGES:

        if current_url in visited_pages:
            print("same page detected. stop.")
            break

        visited_pages.add(current_url)

        print()
        print(
            f"fetch search page: "
            f"{current_url}"
        )

        html = fetch_html(current_url)

        if html is None:
            print("search page fetch failed")
            break

        soup = BeautifulSoup(
            html,
            "lxml"
        )

        page_count += 1
        page_jobs = set()

        # ----------------------------------------
        # 求人詳細URL収集
        # ----------------------------------------

        for a in soup.find_all(
            "a",
            href=True
        ):

            full = urljoin(
                current_url,
                a["href"]
            )

            if "/viewjob/" not in full:
                continue

            if not globals().get("ALLOW_QUERY_JOB_LINKS", True) and urlparse(full).query:
                continue

            clean = normalize_job_url(
                full
            )

            page_jobs.add(clean)
            all_jobs.add(clean)

        print(
            f"page={page_count} "
            f"page_jobs={len(page_jobs)} "
            f"unique_jobs={len(all_jobs)}"
        )

        # ----------------------------------------
        # 次へ
        # ----------------------------------------

        next_link = soup.find(
            "a",
            attrs={"aria-label": "次へ"}
        )

        if not next_link:
            print(
                "next page not found. finish."
            )
            break

        candidate_url = urljoin(
            current_url,
            next_link["href"]
        )

        parsed = urlparse(
            candidate_url
        )

        query = parse_qs(
            parsed.query
        )

        # 同一検索条件 + cursor のみ許可
        if (
            parsed.path != TARGET_PATH
            or "cursor" not in query
        ):
            print(
                "invalid next page detected. finish:",
                candidate_url
            )
            break

        if candidate_url in visited_pages:
            print(
                "next page already visited. finish."
            )
            break

        current_url = candidate_url

        time.sleep(
            REQUEST_INTERVAL
        )

    return sorted(all_jobs), page_count


# ============================================================
# JSON-LD取得
# ============================================================

def find_jobposting_jsonld(soup):

    scripts = soup.find_all(
        "script",
        attrs={
            "type": "application/ld+json"
        }
    )

    for script in scripts:

        raw = (
            script.string
            or script.get_text()
        )

        if not raw:
            continue

        try:
            data = json.loads(raw)

        except json.JSONDecodeError:
            continue

        if isinstance(data, dict):

            if data.get("@type") == "JobPosting":
                return data

            graph = data.get("@graph")

            if isinstance(graph, list):

                for item in graph:

                    if (
                        isinstance(item, dict)
                        and item.get("@type")
                        == "JobPosting"
                    ):
                        return item

        elif isinstance(data, list):

            for item in data:

                if (
                    isinstance(item, dict)
                    and item.get("@type")
                    == "JobPosting"
                ):
                    return item

    return None


# ============================================================
# HTML内の想定年収取得
# ============================================================

def extract_expected_salary_from_html(html):
    """
    HTML内の「想定年収」を抽出する。

    対応例:
    420万円～1,000万円
    550万円～
    532万円～532万円
    """

    pattern = re.compile(
        r'想定年収\\n'
        r'([0-9,]+)万円'
        r'(?:～([0-9,]+)万円)?'
    )

    match = pattern.search(html)

    if not match:
        return {
            "salary_min": None,
            "salary_max": None,
        }

    salary_min = int(
        match.group(1).replace(",", "")
    )

    salary_max = (
        int(match.group(2).replace(",", ""))
        if match.group(2)
        else None
    )

    return {
        "salary_min": salary_min,
        "salary_max": salary_max,
    }


# ============================================================
# HTML description → text
# ============================================================

def description_to_text(value):

    if value is None:
        return ""

    if not isinstance(value, str):
        value = str(value)

    soup = BeautifulSoup(
        value,
        "lxml"
    )

    return soup.get_text(
        "\n",
        strip=True
    )


# ============================================================
# location正規化
# ============================================================

def normalize_job_location(raw_location):

    regions = []
    locations = []

    if isinstance(raw_location, dict):

        raw_location = [
            raw_location
        ]

    if isinstance(raw_location, list):

        for item in raw_location:

            if not isinstance(
                item,
                dict
            ):
                continue

            address = (
                item.get("address")
                or {}
            )

            if not isinstance(
                address,
                dict
            ):
                continue

            region = address.get(
                "addressRegion"
            )

            locality = address.get(
                "addressLocality"
            )

            street = address.get(
                "streetAddress"
            )

            if region:
                regions.append(region)

            location_parts = []

            if locality:
                location_parts.append(
                    locality
                )

            if street:
                location_parts.append(
                    street
                )

            if location_parts:
                locations.append(
                    " ".join(location_parts)
                )

    regions = list(
        dict.fromkeys(regions)
    )

    locations = list(
        dict.fromkeys(locations)
    )

    return {
        "region": (
            " / ".join(regions)
            if regions
            else None
        ),

        "location": (
            " / ".join(locations)
            if locations
            else None
        ),

        "regions": regions,

        "locations": locations,
    }


# ============================================================
# JSON-LD salary正規化
# ============================================================

def normalize_salary(base_salary):
    """
    JSON-LDのbaseSalaryを正規化。

    YEARの場合だけ年収として利用する。
    MONTHの場合は勝手に12倍せず、
    raw情報のみ保持する。
    """

    salary_min = None
    salary_max = None
    salary_unit = None
    salary_currency = None

    if not isinstance(
        base_salary,
        dict
    ):
        return {
            "salary_min": None,
            "salary_max": None,
            "salary_unit": None,
            "salary_currency": None,
            "salary_raw": base_salary,
        }

    salary_currency = (
        base_salary.get("currency")
    )

    value = (
        base_salary.get("value")
        or {}
    )

    if not isinstance(
        value,
        dict
    ):
        return {
            "salary_min": None,
            "salary_max": None,
            "salary_unit": None,
            "salary_currency": salary_currency,
            "salary_raw": base_salary,
        }

    salary_unit = (
        value.get("unitText")
    )

    raw_min = value.get(
        "minValue"
    )

    raw_max = value.get(
        "maxValue"
    )

    raw_value = value.get(
        "value"
    )

    # ----------------------------------------
    # YEAR
    # ----------------------------------------

    if salary_unit == "YEAR":

        if raw_min is not None:
            salary_min = round(
                float(raw_min) / 10000
            )

        if raw_max is not None:
            salary_max = round(
                float(raw_max) / 10000
            )

        if (
            raw_value is not None
            and salary_min is None
            and salary_max is None
        ):
            salary_min = round(
                float(raw_value) / 10000
            )

    # ----------------------------------------
    # MONTH
    # ----------------------------------------

    elif salary_unit == "MONTH":

        salary_min = None
        salary_max = None

    return {
        "salary_min": (
            int(salary_min)
            if salary_min is not None
            else None
        ),

        "salary_max": (
            int(salary_max)
            if salary_max is not None
            else None
        ),

        "salary_unit": salary_unit,

        "salary_currency": salary_currency,

        "salary_raw": base_salary,
    }


# ============================================================
# JobPosting正規化
# ============================================================

def normalize_jobposting(
    data,
    source_url,
    expected_salary
):

    organization = (
        data.get("hiringOrganization")
        or {}
    )

    company_name = None

    if isinstance(
        organization,
        dict
    ):
        company_name = (
            organization.get("name")
        )

    location = normalize_job_location(
        data.get("jobLocation")
    )

    salary = normalize_salary(
        data.get("baseSalary")
    )

    identifier = (
        data.get("identifier")
        or {}
    )

    external_id = None

    if isinstance(
        identifier,
        dict
    ):
        raw_id = identifier.get(
            "value"
        )

        if raw_id is not None:
            external_id = str(
                raw_id
            )

    description = description_to_text(
        data.get("description")
    )

    # ----------------------------------------
    # 年収優先順位
    #
    # 1. HTML内の「想定年収」
    # 2. JSON-LD YEAR
    # 3. null
    # ----------------------------------------

    final_salary_min = (
        expected_salary["salary_min"]
        if expected_salary["salary_min"] is not None
        else salary["salary_min"]
    )

    final_salary_max = (
        expected_salary["salary_max"]
        if expected_salary["salary_max"] is not None
        else salary["salary_max"]
    )

    return {
        "company_url_evidence": extract_company_url_evidence(data, company_name, source_url, "recruit_agent"),
        "external_id": external_id,

        "company_name": company_name,

        "title": (
            data.get("title")
        ),

        # Recruit検索条件が「機械設計」のため
        # いったん検索由来の職種として保持
        "occupation": (
            "機械設計"
        ),

        "region": (
            location["region"]
        ),

        "location": (
            location["location"]
        ),

        "regions": (
            location["regions"]
        ),

        "locations": (
            location["locations"]
        ),

        "salary_min": (
            final_salary_min
        ),

        "salary_max": (
            final_salary_max
        ),

        "salary_source": (
            "expected_salary_html"
            if expected_salary["salary_min"] is not None
            else (
                "jsonld_year"
                if salary["salary_min"] is not None
                else None
            )
        ),

        "salary_unit": (
            salary["salary_unit"]
        ),

        "salary_currency": (
            salary["salary_currency"]
        ),

        "salary_raw": (
            salary["salary_raw"]
        ),

        "employment_type": (
            data.get(
                "employmentType"
            )
        ),

        "date_posted": (
            data.get(
                "datePosted"
            )
        ),

        "industry": (
            data.get(
                "industry"
            )
        ),

        "source_url": (
            data.get("url")
            or source_url
        ),

        "source_provider": (
            "リクルートエージェント"
        ),

        "agency_name": (
            "リクルートエージェント"
        ),

        "route_type": (
            "agent"
        ),

        "description": (
            description
        ),

        "description_length": (
            len(description)
        ),
    }


# ============================================================
# 詳細ページ取得
# ============================================================

def fetch_job_detail(
    url,
    index,
    total
):

    print()
    print(
        f"[{index}/{total}] "
        f"fetch job: {url}"
    )

    html = fetch_html(url)

    if html is None:

        print(
            f"[{index}/{total}] "
            "FAILED"
        )

        return None

    soup = BeautifulSoup(
        html,
        "lxml"
    )

    data = find_jobposting_jsonld(
        soup
    )

    if data is None:

        print(
            f"[{index}/{total}] "
            "JobPosting JSON-LD NOT FOUND"
        )

        return None

    expected_salary = (
        extract_expected_salary_from_html(
            html
        )
    )

    try:

        job = normalize_jobposting(
            data,
            url,
            expected_salary
        )

    except Exception as e:

        print(
            f"[{index}/{total}] "
            "NORMALIZE ERROR"
        )

        print(
            f"{type(e).__name__}: "
            f"{e}"
        )

        return None

    salary_min = (
        job.get("salary_min")
    )

    salary_max = (
        job.get("salary_max")
    )

    if (
        salary_min is not None
        and salary_max is not None
    ):
        salary_display = (
            f"{salary_min}-{salary_max}万円"
        )

    elif salary_min is not None:
        salary_display = (
            f"{salary_min}万円以上"
        )

    elif salary_max is not None:
        salary_display = (
            f"{salary_max}万円以下"
        )

    else:
        salary_display = (
            "年収未確認"
        )

    job["company_url_evidence"] = extract_company_url_evidence(
        data, job.get("company_name"), url, "recruit_agent", html=html
    )

    print(
        f"[{index}/{total}] "
        f"{job.get('company_name')} | "
        f"{job.get('title')} | "
        f"{job.get('region')} | "
        f"{salary_display} | "
        f"source={job.get('salary_source')} | "
        f"base_unit={job.get('salary_unit')}"
    )

    return job


# ============================================================
# Checkpoint
# ============================================================

def save_checkpoint(
    jobs,
    failed_urls,
    total,
    page_count,
    completed=False
):

    result = {

        "source_provider": (
            "リクルートエージェント"
        ),

        "agency_name": (
            "リクルートエージェント"
        ),

        "search_url": (
            SEARCH_URL
        ),

        "search_conditions": {
            "occupation": (
                "機械設計"
            ),

            "region": (
                "兵庫県"
            ),

            "salary_min": None,
        },

        "fetched_at": (
            datetime.now(
                timezone.utc
            ).isoformat()
        ),

        "page_count": (
            page_count
        ),

        "discovered_job_count": (
            total
        ),

        "success_count": (
            len(jobs)
        ),

        "failed_count": (
            len(failed_urls)
        ),

        "failed_urls": (
            failed_urls
        ),

        "completed": (
            completed
        ),

        "jobs": jobs,
    }

    output_path.parent.mkdir(
        parents=True,
        exist_ok=True
    )

    output_path.write_text(
        json.dumps(
            result,
            ensure_ascii=False,
            indent=2
        ),
        encoding="utf-8"
    )


# ============================================================
# main
# ============================================================

def main():

    print(
        "=== Recruit Agent crawler start ==="
    )

    print()
    print(
        "search condition:"
        " 機械設計 × 兵庫県"
    )

    # ----------------------------------------
    # STEP 1
    # 一覧
    # ----------------------------------------

    job_urls, page_count = (
        collect_job_urls()
    )

    total = len(job_urls)

    print()
    print(
        "=== search result collected ==="
    )

    print(
        f"pages: {page_count}"
    )

    print(
        f"job urls: {total}"
    )

    # ----------------------------------------
    # STEP 2
    # 詳細
    # ----------------------------------------

    jobs = []
    failed_urls = []

    for index, url in enumerate(
        job_urls,
        1
    ):

        job = fetch_job_detail(
            url,
            index,
            total
        )

        if job is None:
            failed_urls.append(
                url
            )

        else:
            jobs.append(
                job
            )

        save_checkpoint(
            jobs=jobs,
            failed_urls=failed_urls,
            total=total,
            page_count=page_count,
            completed=False
        )

        time.sleep(
            REQUEST_INTERVAL
        )

    # ----------------------------------------
    # STEP 3
    # 最終保存
    # ----------------------------------------

    save_checkpoint(
        jobs=jobs,
        failed_urls=failed_urls,
        total=total,
        page_count=page_count,
        completed=True
    )

    # ----------------------------------------
    # STEP 4
    # 年収取得件数集計
    # ----------------------------------------

    salary_found_count = sum(
        1
        for job in jobs
        if job.get("salary_min") is not None
    )

    salary_missing_count = (
        len(jobs)
        - salary_found_count
    )

    expected_salary_count = sum(
        1
        for job in jobs
        if job.get("salary_source")
        == "expected_salary_html"
    )

    jsonld_year_count = sum(
        1
        for job in jobs
        if job.get("salary_source")
        == "jsonld_year"
    )

    print()
    print(
        "=============================="
    )

    print(
        "=== completed ==="
    )

    print(
        f"pages: {page_count}"
    )

    print(
        f"discovered jobs: {total}"
    )

    print(
        f"success: {len(jobs)}"
    )

    print(
        f"failed: {len(failed_urls)}"
    )

    print()
    print(
        "=== salary summary ==="
    )

    print(
        f"salary found: "
        f"{salary_found_count}"
    )

    print(
        f"salary missing: "
        f"{salary_missing_count}"
    )

    print(
        f"expected salary html: "
        f"{expected_salary_count}"
    )

    print(
        f"jsonld year: "
        f"{jsonld_year_count}"
    )

    print()
    print(
        f"saved: {output_path}"
    )

    print(
        "=============================="
    )


if __name__ == "__main__":
    main()