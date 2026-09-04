import json
import requests
from bs4 import BeautifulSoup
from pathlib import Path

url = "https://hrmos.co/pages/mhi/jobs/2253388145558663168"

headers = {
    "User-Agent": "Mozilla/5.0"
}

response = requests.get(url, headers=headers, timeout=15)
response.raise_for_status()

soup = BeautifulSoup(response.text, "lxml")

lines = [
    line.strip()
    for line in soup.get_text("\n", strip=True).split("\n")
    if line.strip()
]


def value_after(label):
    try:
        index = lines.index(label)
        return lines[index + 1]
    except (ValueError, IndexError):
        return None


job = {
    "company_name": "三菱重工業株式会社",
    "title": value_after("職種 / 募集ポジション"),
    "employment_type": value_after("雇用形態"),
    "salary": value_after("給与"),
    "location": value_after("勤務地"),
    "source_url": url,
}

print(json.dumps(job, ensure_ascii=False, indent=2))

project_root = Path(__file__).resolve().parent.parent
output_path = project_root / "storage/app/private/crawler/mhi_job.json"

output_path.parent.mkdir(parents=True, exist_ok=True)

output_path.write_text(
    json.dumps(job, ensure_ascii=False, indent=2),
    encoding="utf-8"
)

print(f"saved: {output_path}")