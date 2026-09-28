"""Representative image metadata only; no requests or image-file downloads."""
import json
import re
from urllib.parse import urljoin, urlparse


def safe_image_url(value, page_url):
    if not isinstance(value, str) or not value.strip():
        return None
    value = value.strip()
    if re.search(r'[\s\\\x00-\x1f]', value):
        return None
    try:
        url = urljoin(page_url, value)
        parsed = urlparse(url)
        if parsed.scheme not in ('http', 'https') or not parsed.hostname or parsed.username or parsed.password:
            return None
        parsed.port  # Reject malformed port values.
        return url
    except ValueError:
        return None


def extract_eyecatch(soup, page_url):
    """og:image, then JobPosting/WebPage image; never arbitrary body images/logos."""
    for meta in soup.find_all('meta', attrs={'property': 'og:image'}):
        if url := safe_image_url(meta.get('content'), page_url):
            return url

    def nodes(value):
        if isinstance(value, list):
            for item in value:
                yield from nodes(item)
        elif isinstance(value, dict):
            yield value
            yield from nodes(value.get('@graph'))

    def images(value):
        if isinstance(value, list):
            for item in value:
                yield from images(item)
        elif isinstance(value, dict):
            yield value.get('contentUrl') or value.get('url')
        else:
            yield value

    for script in soup.find_all('script', attrs={'type': 'application/ld+json'}):
        try:
            data = json.loads(script.string or script.get_text())
        except (ValueError, TypeError):
            continue
        for node in nodes(data):
            types = node.get('@type', [])
            types = [types] if isinstance(types, str) else types
            if not isinstance(types, list) or not any(t in ('JobPosting', 'WebPage') for t in types):
                continue
            for value in images(node.get('image')):
                if url := safe_image_url(value, page_url):
                    return url
    return None
