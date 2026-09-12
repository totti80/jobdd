import json
import os
import time
from datetime import datetime, timezone
from pathlib import Path

import requests


API_URL = "https://search.api.careerjet.net/v4/query"
REGIONS = ["大阪府", "兵庫県", "京都府", "滋賀県", "奈良県", "和歌山県"]
OCCUPATIONS = ["機械設計", "電気設計", "施工管理"]
PAGE_SIZE = min(int(os.getenv("CAREERJET_PAGE_SIZE", "100")), 100)
MAX_PAGES = min(int(os.getenv("CAREERJET_MAX_PAGES", "3")), 3)
REQUEST_INTERVAL = float(os.getenv("CAREERJET_REQUEST_INTERVAL", "1.0"))


def fetch_cell(session: requests.Session, region: str, occupation: str) -> list[dict]:
    jobs: list[dict] = []
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
                "user_agent": os.getenv("USER_AGENT", "JobDD-MVP-Crawler/1.0"),
            },
            auth=(os.environ["CAREERJET_API_KEY"], ""),
            headers={"Referer": "https://jobdd.jp/find-jobs/", "Accept": "application/json"},
            timeout=20,
        )
        response.raise_for_status()
        data = response.json()
        page_jobs = data.get("jobs", [])
        for job in page_jobs:
            job["region"] = region
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