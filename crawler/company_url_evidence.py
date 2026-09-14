"""Preserve observed company URL fields only; never infer domains or official identity."""
import json
from datetime import datetime, timezone
from urllib.parse import urlparse

from bs4 import BeautifulSoup

FIELDS = ("company_url", "company_website_url", "employer_url", "corporate_site_url",
          "official_site_url", "website_url", "homepage_url")
LABELS = ("企業公式サイト", "会社ホームページ", "企業ホームページ", "コーポレートサイト",
          "corporate website", "corporate site", "company website", "official website")


def extract_company_url_evidence(data, company_name, source_url, provider, html=None):
    found = []
    fetched_at = datetime.now(timezone.utc).isoformat()

    def add(value, field, name, label=None):
        if not isinstance(value, str) or len(value) > 2048 or not company_name:
            return
        try:
            parsed = urlparse(value)
        except ValueError:
            return
        if parsed.scheme not in ("http", "https") or not parsed.hostname:
            return
        if name != company_name or len(found) >= 40:
            return
        entry = dict(source_provider=provider, source_url=source_url,
                     company_name=name, raw_field=field, raw_value=value,
                     fetched_at=fetched_at, review_status="needs_review")
        if label:
            entry["link_text"] = label[:200]
        if entry not in found:
            found.append(entry)

    def walk(node, path, depth=0):
        if depth > 12:
            return
        if isinstance(node, list):
            for i, value in enumerate(node):
                walk(value, f"{path}[{i}]", depth + 1)
        elif isinstance(node, dict):
            types = node.get("@type", [])
            types = [types] if isinstance(types, str) else types
            is_org = (isinstance(types, list) and any(t in types for t in
                      ("Organization", "Corporation", "LocalBusiness")))
            is_org = is_org or path.endswith((".hiringOrganization", ".organization", ".employer"))
            if is_org and node.get("name") == company_name:
                for field in ("url", "sameAs", *FIELDS):
                    raw = node.get(field, [])
                    values = raw
                    values = values if isinstance(values, list) else [values]
                    for i, value in enumerate(values):
                        suffix = f"[{i}]" if isinstance(raw, list) else ""
                        add(value, f"{path}.{field}{suffix}", node["name"])
            for key, value in node.items():
                if isinstance(value, (dict, list)):
                    walk(value, f"{path}.{key}", depth + 1)

    walk(data, "$")
    if isinstance(data, dict):
        for field in FIELDS:
            add(data.get(field), f"$.{field}", company_name)
    # Only explicit corporate labels, not arbitrary external links or canonical URLs.
    # No full HTML is persisted.
    for origin, content in (("html", html), ("$.description", data.get("description") if isinstance(data, dict) else None)):
        if not isinstance(content, str) or len(content) > 2 * 1024 * 1024:
            continue
        soup = BeautifulSoup(content, "html.parser")
        for i, script in enumerate(soup.find_all("script", type="application/ld+json")):
            try:
                walk(json.loads(script.get_text()), f"{origin}.jsonld[{i}]")
            except (ValueError, TypeError):
                pass
        for i, link in enumerate(soup.find_all("a", href=True)):
            label = link.get_text(" ", strip=True)
            if any(word in label.lower() for word in LABELS):
                add(link["href"], f"{origin}.a[{i}].href", company_name, label)
    return found
