import json
import os
import time
from datetime import datetime, timezone
from pathlib import Path

import requests
from company_url_evidence import extract_company_url_evidence


API_URL = "https://search.api.careerjet.net/v4/query"
REGIONS = ["大阪府", "兵庫県", "京都府", "滋賀県", "奈良県", "和歌山県"]
OCCUPATIONS = ["機械設計", "電気設計", "施工管理"]
PAGE_SIZE = min(int(os.getenv("CAREERJET_PAGE_SIZE", "20")), 20)
MAX_PAGES = min(int(os.getenv("CAREERJET_MAX_PAGES", "3")), 3)
REQUEST_INTERVAL = float(os.getenv("CAREERJET_REQUEST_INTERVAL", "1.0"))


def fetch_cell(session: requests.Session, region: str, occupation: str, metadata=None) -> list[dict]:
    jobs: list[dict] = []
    observed_pages = set()
    for page in range(1, MAX_PAGES + 1):
        response = session.get(
            API_URL,
            params={
                "locale_code": "ja_JP",
                "keywords": occupation,
                "location": region,
                "contract_type": "p",
                "page": page,
                "page_size": PAGE_SIZE,
                "user_ip": os.getenv("USER_IP", "127.0.0.1"),
                "user_agent": os.getenv("USER_AGENT", "Mozilla/5.0"),
            },
            auth=(os.environ["CAREERJET_API_KEY"], ""),
            headers={"Referer": "https://jobdd.jp/find-jobs/", "Accept": "application/json"},
            timeout=20,
        )
        response.raise_for_status()
        data = response.json()
        if not isinstance(data, dict) or not isinstance(data.get("jobs"), list):
            raise ValueError("Invalid Careerjet jobs response")
        if metadata is not None and isinstance(data.get("hits"), int):
            metadata["hits"] = data["hits"]
        page_jobs = data["jobs"]
        # A content signature is a pagination guard only, never a persisted identity.
        # Tracking URLs may change even when the API repeats exactly the same page.
        page_signature = tuple(sorted(json.dumps(
            {key: job.get(key) for key in ("id", "external_id", "title", "company", "locations", "description", "salary_min", "salary_max", "salary_type", "site")},
            sort_keys=True, ensure_ascii=False,
        ) for job in page_jobs))
        if page_signature and page_signature in observed_pages:
            raise ValueError("RepeatedCareerjetPage")
        observed_pages.add(page_signature)
        for job in page_jobs:
            job["company_url_evidence"] = extract_company_url_evidence(
                job, job.get("company"), job.get("url"), "careerjet"
            )
            job["region"] = job.get("locations")
            job["search_region"] = region
            job["search_occupation"] = occupation
            jobs.append(job)

        if len(page_jobs) < PAGE_SIZE:
            break
        time.sleep(REQUEST_INTERVAL)
    return jobs


def main() -> None:
    output_path = Path(__file__).resolve().parent.parent / "storage/app/private/crawler/careerjet_jobs.json"
    output_path.parent.mkdir(parents=True, exist_ok=True)
    session = requests.Session()
    all_jobs: list[dict] = []
    cells: list[dict] = []

    for region in REGIONS:
        for occupation in OCCUPATIONS:
            jobs = fetch_cell(session, region, occupation)
            all_jobs.extend(jobs)
            cells.append({"region": region, "occupation": occupation, "fetched_count": len(jobs)})
            print(f"{region} / {occupation}: {len(jobs)}")
            time.sleep(REQUEST_INTERVAL)

    payload = {
        "completed": True,
        "provider_key": "careerjet",
        "fetched_at": datetime.now(timezone.utc).isoformat(),
        "page_size": PAGE_SIZE,
        "max_pages": MAX_PAGES,
        "cells": cells,
        "jobs": all_jobs,
    }
    output_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"saved: {output_path} jobs={len(all_jobs)}")


if __name__ == "__main__":
    main()