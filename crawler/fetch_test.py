import requests
from bs4 import BeautifulSoup

url = "https://hrmos.co/pages/mhi/jobs/2253388145558663168"

headers = {
    "User-Agent": "Mozilla/5.0"
}

response = requests.get(url, headers=headers, timeout=15)
response.raise_for_status()

soup = BeautifulSoup(response.text, "lxml")

print("status:", response.status_code)
print("title:", soup.title.string.strip() if soup.title else "No title")
print()

# ページ内テキストを確認
text = soup.get_text("\n", strip=True)

print(text[:5000])