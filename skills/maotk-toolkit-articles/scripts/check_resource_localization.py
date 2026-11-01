#!/usr/bin/env python3
"""Read-only scan for legacy-host references in article source and metadata."""
import argparse
import html
import json
import re
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlparse

URL = re.compile(r'(?:https?:)?//[^\s<>"\'\\]+', re.I)
FILE = re.compile(r'\.(?:pdf|zip|7z|rar|tar(?:\.gz)?|mp4|webm|mp3|exe|dmg|apk)(?:[?#]|$)', re.I)


def urls(text):
    return URL.findall(html.unescape(text.replace('\\/', '/')))


def inspect(path, hosts):
    text = path.read_text(encoding='utf-8-sig')
    found = {}

    def record(url, kind, line=None):
        candidate = 'https:' + url if url.startswith('//') else url
        host = (urlparse(candidate).hostname or '').lower().rstrip('.')
        if host not in hosts:
            return
        key = (url, kind)
        found.setdefault(key, {'file': str(path), 'line': line, 'kind': kind, 'url': url})

    class Tags(HTMLParser):
        def handle_starttag(self, tag, attrs):
            for key, value in attrs:
                if not value:
                    continue
                if key in {'src', 'srcset', 'poster', 'data-src', 'data-original', 'data-link-preview-image'}:
                    kind = 'resource'
                elif key == 'href':
                    kind = 'download' if FILE.search(value) else 'reference'
                else:
                    kind = 'embedded-url'
                for url in urls(value):
                    record(url.rstrip(','), kind, self.getpos()[0])

    parser = Tags(convert_charrefs=True)
    parser.feed(text)
    attributed = {item['url'] for item in found.values()}
    for number, line in enumerate(text.splitlines(), 1):
        for url in urls(line):
            url = url.rstrip(',')
            if url not in attributed:
                record(url, 'source-or-metadata', number)
    return list(found.values())


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('source', type=Path)
    ap.add_argument('--old-host', action='append', required=True, help='Legacy hostname; repeat for multiple hosts.')
    ap.add_argument('--metadata', type=Path, action='append', default=[], help='Optional local JSON/text metadata; repeatable.')
    args = ap.parse_args()
    hosts = set()
    for value in args.old_host:
        host = (urlparse(value if '://' in value else 'https://' + value).hostname or '').lower().rstrip('.')
        if not host:
            ap.error('Invalid --old-host: ' + value)
        hosts.add(host)
    findings = []
    for path in [args.source, *args.metadata]:
        findings.extend(inspect(path, hosts))
    print(json.dumps({'result': 'REVIEW' if findings else 'PASS', 'legacy_hosts': sorted(hosts),
                      'findings': findings, 'network_checked': False}, ensure_ascii=False, indent=2))
    return 1 if findings else 0


if __name__ == '__main__':
    raise SystemExit(main())
