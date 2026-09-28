import json
import os
from eyecatch import extract_eyecatch
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin, urlparse

import requests
from company_url_evidence import extract_company_url_evidence
from bs4 import BeautifulSoup


# ============================================================
# 基本設定
# ============================================================

BASE_URL = "https://www.m-next.jp"

# 機械設計 × 近畿圏
SEARCH_URL = (
    "https://www.m-next.jp/"
    "job/s/j16+16020/a440/"
)

HEADERS = {
    "User-Agent": "Mozilla/5.0"
}

# サイトへの連続アクセスを避ける
REQUEST_INTERVAL = 1.0

# HTTP失敗時の再試行回数
MAX_RETRIES = 3


# ============================================================
# 保存先
# ============================================================

project_root = Path(__file__).resolve().parent.parent

output_path = (
    project_root
    / "storage/app/private/crawler/meitec_next_jobs.json"
)


# ============================================================
# URL処理
# ============================================================

def normalize_url(url):
    """
    #paging 等のfragmentを除去し、
    末尾スラッシュを統一する。
    """

    parsed = urlparse(url)

    path = parsed.path.rstrip("/") + "/"

    return parsed._replace(
        path=path,
        fragment=""
    ).geturl()


def is_job_detail_url(url):
    """
    メイテックネクスト求人詳細URLか判定。

    例:
    /job/mechanical/mechanicaldesign/263263/
    """

    parsed = urlparse(url)

    parts = [
        part
        for part in parsed.path.split("/")
        if part
    ]

    if len(parts) < 4:
        return False

    if parts[0] != "job":
        return False

    # URL末尾が求人ID
    return parts[-1].isdigit()


def is_search_result_url(url):
    """
    今回の検索条件配下のページか判定。
    """

    parsed = urlparse(url)

    return parsed.path.startswith(
        "/job/s/j16+16020/a440/"
    )


# ============================================================
# HTTP
# ============================================================

session = requests.Session()
session.headers.update(HEADERS)


def fetch_html(url):
    """
    HTMLを取得。
    一時的な失敗時は最大3回まで再試行。
    """

    for attempt in range(1, MAX_RETRIES + 1):

        try:

            response = session.get(
                url,
                timeout=20
            )

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
# 一覧ページから求人URL収集
# ============================================================

def collect_job_urls():

    pages_to_visit = [
        normalize_url(SEARCH_URL)
    ]

    visited_pages = set()

    job_urls = set()

    page_count = 0

    while pages_to_visit:

        page_url = pages_to_visit.pop(0)

        page_url = normalize_url(page_url)

        if page_url in visited_pages:
            continue

        visited_pages.add(page_url)

        print()
        print(
            f"fetch search page: "
            f"{page_url}"
        )

        html = fetch_html(page_url)

        if html is None:
            print("search page fetch failed")
            continue

        page_count += 1

        soup = BeautifulSoup(
            html,
            "lxml"
        )

        # ----------------------------------------
        # 求人詳細URL収集
        # ----------------------------------------

        for link in soup.find_all(
            "a",
            href=True
        ):

            href = link.get("href")

            if not href:
                continue

            full_url = normalize_url(
                urljoin(
                    BASE_URL,
                    href
                )
            )

            if not is_job_detail_url(full_url):
                continue

            job_urls.add(full_url)

        # ----------------------------------------
        # ページネーション探索
        # ----------------------------------------

        for link in soup.find_all(
            "a",
            href=True
        ):

            href = link.get("href")

            if not href:
                continue

            full_url = normalize_url(
                urljoin(
                    BASE_URL,
                    href
                )
            )

            if not is_search_result_url(full_url):
                continue

            if full_url in visited_pages:
                continue

            if full_url in pages_to_visit:
                continue

            pages_to_visit.append(full_url)

        print(
            f"pages={page_count} "
            f"job_urls={len(job_urls)}"
        )

        time.sleep(
            REQUEST_INTERVAL
        )

    return sorted(job_urls), page_count


# ============================================================
# JSON-LD取得
# ============================================================

def find_jobposting_jsonld(soup):
    """
    JSON-LDから JobPosting を取得。
    """

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

        # ----------------------------------------
        # 単一Object
        # ----------------------------------------

        if isinstance(data, dict):

            if data.get("@type") == "JobPosting":
                return data

            # @graph対応
            graph = data.get("@graph")

            if isinstance(graph, list):

                for item in graph:

                    if (
                        isinstance(item, dict)
                        and item.get("@type")
                        == "JobPosting"
                    ):
                        return item

        # ----------------------------------------
        # JSON-LDが配列
        # ----------------------------------------

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
# 年収変換
# ============================================================

def yen_to_man(value):
    """
    4,700,000円 -> 470万円
    """

    if value is None:
        return None

    try:

        return int(
            round(
                float(value) / 10000
            )
        )

    except (
        TypeError,
        ValueError
    ):

        return None


# ============================================================
# jobLocation正規化
# ============================================================

def normalize_job_location(raw_location):
    """
    jobLocation が dict / list の両方に対応。

    戻り値:
    {
        "region": "...",
        "location": "...",
        "regions": [...],
        "locations": [...]
    }
    """

    regions = []
    locations = []

    # ----------------------------------------
    # dictの場合
    # ----------------------------------------

    if isinstance(raw_location, dict):

        address = (
            raw_location.get("address")
            or {}
        )

        if isinstance(address, dict):

            region = address.get(
                "addressRegion"
            )

            locality = address.get(
                "addressLocality"
            )

            if region:
                regions.append(region)

            if locality:
                locations.append(locality)

    # ----------------------------------------
    # listの場合
    # ----------------------------------------

    elif isinstance(raw_location, list):

        for location_item in raw_location:

            if not isinstance(
                location_item,
                dict
            ):
                continue

            address = (
                location_item.get("address")
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

            if region:
                regions.append(region)

            if locality:
                locations.append(locality)

    # 重複除去・順序維持
    regions = list(
        dict.fromkeys(regions)
    )

    locations = list(
        dict.fromkeys(locations)
    )

    # JobDD既存DB用の代表値
    region_text = (
        " / ".join(regions)
        if regions
        else None
    )

    location_text = (
        " / ".join(locations)
        if locations
        else None
    )

    return {
        "region": region_text,
        "location": location_text,
        "regions": regions,
        "locations": locations,
    }


# ============================================================
# JobPosting構造化
# ============================================================

def normalize_jobposting(
    data,
    source_url
):

    # ----------------------------------------
    # hiringOrganization
    # ----------------------------------------

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

    # ----------------------------------------
    # jobLocation
    # ----------------------------------------

    raw_location = (
        data.get("jobLocation")
        or {}
    )

    normalized_location = (
        normalize_job_location(
            raw_location
        )
    )

    # ----------------------------------------
    # salary
    # ----------------------------------------

    base_salary = (
        data.get("baseSalary")
        or {}
    )

    salary_currency = None
    salary_value = {}

    if isinstance(
        base_salary,
        dict
    ):

        salary_currency = (
            base_salary.get("currency")
        )

        salary_value = (
            base_salary.get("value")
            or {}
        )

    if not isinstance(
        salary_value,
        dict
    ):

        salary_value = {}

    # ----------------------------------------
    # identifier
    # ----------------------------------------

    identifier = (
        data.get("identifier")
        or {}
    )

    external_id = None

    if isinstance(
        identifier,
        dict
    ):

        value = (
            identifier.get("value")
        )

        if value is not None:
            external_id = str(value)

    # ----------------------------------------
    # description
    # ----------------------------------------

    description = (
        data.get("description")
        or ""
    )

    if not isinstance(
        description,
        str
    ):

        description = str(
            description
        )

    # ----------------------------------------
    # 正規化結果
    # ----------------------------------------

    return {
        "company_url_evidence": extract_company_url_evidence(data, company_name, source_url, "meitec_next"),

        "external_id": (
            external_id
        ),

        "company_name": (
            company_name
        ),

        "title": (
            data.get("title")
        ),

        # 今回の検索条件
        "occupation": (
            "機械設計"
        ),

        # 既存DB用
        "region": (
            normalized_location[
                "region"
            ]
        ),

        "location": (
            normalized_location[
                "location"
            ]
        ),

        # 複数勤務地保持用
        "regions": (
            normalized_location[
                "regions"
            ]
        ),

        "locations": (
            normalized_location[
                "locations"
            ]
        ),

        "salary_min": (
            yen_to_man(
                salary_value.get(
                    "minValue"
                )
            )
        ),

        "salary_max": (
            yen_to_man(
                salary_value.get(
                    "maxValue"
                )
            )
        ),

        "salary_currency": (
            salary_currency
        ),

        "salary_unit": (
            salary_value.get(
                "unitText"
            )
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

        "source_url": (
            data.get("url")
            or source_url
        ),

        "source_provider": (
            "メイテックネクスト"
        ),

        "agency_name": (
            "株式会社メイテックネクスト"
        ),

        "route_type": (
            "agent"
        ),

        # Skill Evidence抽出等に利用可能
        # DBへの全文恒久保存は別途判断
        "description": (
            description
        ),

        "description_length": (
            len(description)
        ),
    }


# ============================================================
# 求人詳細取得
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

    try:

        job = normalize_jobposting(
            data,
            url
        )

    except Exception as e:

        print(
            f"[{index}/{total}] "
            "NORMALIZE ERROR"
        )

        print(
            f"{type(e).__name__}: {e}"
        )

        return None

    job["eyecatch_image_url"] = None
    if "meitec_next" in os.getenv("JOBDD_EYECATCH_APPROVED_PROVIDERS", "").split(","):
        try:
            job["eyecatch_image_url"] = extract_eyecatch(soup, url)
        except Exception:
            # Optional metadata must never fail a job import.
            pass

    job["company_url_evidence"] = extract_company_url_evidence(
        data, job.get("company_name"), url, "meitec_next", html=html
    )

    print(
        f"[{index}/{total}] "
        f"{job.get('company_name')} | "
        f"{job.get('title')} | "
        f"{job.get('region')} | "
        f"{job.get('salary_min')}"
        f"-"
        f"{job.get('salary_max')}万円"
    )

    return job


# ============================================================
# Checkpoint保存
# ============================================================

def save_checkpoint(
    jobs,
    failed_urls,
    total,
    page_count,
    completed=False
):

    fetched_at = datetime.now(
        timezone.utc
    ).isoformat()

    result = {

        "source_provider": (
            "メイテックネクスト"
        ),

        "agency_name": (
            "株式会社メイテックネクスト"
        ),

        "search_url": (
            SEARCH_URL
        ),

        "search_conditions": {

            "occupation": (
                "機械設計"
            ),

            "region": (
                "近畿"
            ),

            "prefectures": [
                "滋賀県",
                "京都府",
                "大阪府",
                "兵庫県",
                "奈良県",
                "和歌山県",
            ],

            "salary_min": None,
        },

        "fetched_at": (
            fetched_at
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

        "jobs": (
            jobs
        ),
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
        "=== Meitec Next crawler start ==="
    )

    print()
    print(
        "search condition:"
        " 機械設計 × 近畿圏"
    )

    # --------------------------------------------------------
    # STEP 1
    # 一覧ページ
    # --------------------------------------------------------

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

    # --------------------------------------------------------
    # STEP 2
    # 詳細ページ
    # --------------------------------------------------------

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

        # ----------------------------------------
        # 毎回Checkpoint保存
        # ----------------------------------------

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

    # --------------------------------------------------------
    # STEP 3
    # 最終保存
    # --------------------------------------------------------

    save_checkpoint(
        jobs=jobs,
        failed_urls=failed_urls,
        total=total,
        page_count=page_count,
        completed=True
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

    print(
        f"saved: {output_path}"
    )

    print(
        "=============================="
    )


if __name__ == "__main__":
    main()