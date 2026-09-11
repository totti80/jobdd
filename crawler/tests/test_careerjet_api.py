import json
import os
from pathlib import Path

import requests

url = "https://search.api.careerjet.net/v4/query"

params = {
    "locale_code": "ja_JP",
    "keywords": "機械設計",
    "location": "兵庫県",
    "contract_type": "p",
    "page": 2,
    "page_size": 5,
    "user_ip": os.environ["USER_IP"],
    "user_agent": os.environ["USER_AGENT"],
}

headers = {
    "Referer": "https://jobdd.jp/find-jobs/",
    "Accept": "application/json",
}

response = requests.get(
    url,
    params=params,
    auth=(os.environ["CAREERJET_API_KEY"], ""),
    headers=headers,
    timeout=10,
)

data = response.json()

print("STATUS:", response.status_code)
print("REQUEST_URL:", response.url)
print("HITS:", data.get("hits"))
print("JOBS_RETURNED:", len(data.get("jobs", [])))

for i, job in enumerate(data.get("jobs", []), 1):
    print(
        i,
        "|",
        job.get("title"),
        "|",
        job.get("company"),
        "|",
        job.get("locations"),
    )

jobs = data.get("jobs", [])

output_dir = Path(__file__).resolve().parent.parent / "data"
output_dir.mkdir(parents=True, exist_ok=True)

output_path = output_dir / "careerjet_jobs.json"

with output_path.open("w", encoding="utf-8") as f:
    json.dump(
        jobs,
        f,
        ensure_ascii=False,
        indent=2,
    )

print("JOBS_SAVED:", output_path)
print("SAVED_COUNT:", len(jobs))

jobs_with_company = [
    job
    for job in jobs
    if str(job.get("company", "")).strip()
]

print("COMPANY_PRESENT_COUNT:", len(jobs_with_company))
print("COMPANY_MISSING_COUNT:", len(jobs) - len(jobs_with_company))