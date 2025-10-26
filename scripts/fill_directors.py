#!/usr/bin/env python3
"""
One-time script to populate the `director` column in the `dvds` table
by querying Wikipedia for each title. Reads DB config from api/config.ini.

Usage:
  python scripts/fill_directors.py [--dry-run] [--limit N] [--offset N] [--force] [--sleep S]

Options:
  --dry-run    Do not write updates, only show what would change
  --limit N    Process at most N rows
  --offset N   Skip first N rows
  --force      Overwrite existing director values
  --sleep S    Seconds to sleep between requests (default 1.0)
"""

from __future__ import annotations
import argparse
import configparser
import time
import re
import sys
from typing import Optional

import pymysql
import requests


USER_AGENT = 'realmsofsilver-dvds/1.0 (https://realmsofsilver.com)'


def load_db_config(path: str = '../api/config.ini') -> dict:
    cfg = configparser.ConfigParser()
    if not cfg.read(path):
        raise FileNotFoundError(f"Config file not found or unreadable: {path}")
    if 'database' not in cfg:
        raise KeyError('Missing [database] section in config file')
    db = cfg['database']
    # map keys to expected names for pymysql
    host = db.get('host', '127.0.0.1')
    user = db.get('username', db.get('user'))
    password = db.get('password', db.get('pass', ''))
    database = db.get('dbname', db.get('database'))
    charset = db.get('charset', 'utf8mb4')
    if not user or not database:
        raise KeyError('Database config must include username and dbname')
    return dict(host=host, user=user, password=password, database=database, charset=charset)


def connect_db(cfg: dict):
    return pymysql.connect(host=cfg['host'], user=cfg['user'], password=cfg['password'],
                           database=cfg['database'], charset=cfg.get('charset', 'utf8mb4'),
                           cursorclass=pymysql.cursors.DictCursor, autocommit=True)


def fetch_rows(conn, limit: Optional[int], offset: int):
    with conn.cursor() as cur:
        if limit is None:
            sql = "SELECT dkey, title, year, director FROM dvds ORDER BY dkey ASC"
            cur.execute(sql)
        else:
            sql = "SELECT dkey, title, year, director FROM dvds ORDER BY dkey ASC LIMIT %s OFFSET %s"
            cur.execute(sql, (int(limit), int(offset)))
        return cur.fetchall()


def update_director(conn, dkey: int, director: str):
    with conn.cursor() as cur:
        cur.execute("UPDATE dvds SET director=%s WHERE dkey=%s", (director, dkey))


def http_get(url: str, params: dict = None) -> Optional[requests.Response]:
    try:
        r = requests.get(url, params=params, headers={'User-Agent': USER_AGENT}, timeout=15)
        r.raise_for_status()
        return r
    except requests.RequestException as e:
        print(f"HTTP request failed: {e}", file=sys.stderr)
        return None


def fetch_director_from_wikipedia(title: str, year: Optional[str] = None) -> Optional[str]:
    # 1) Search
    q = title
    if year:
        q = f"{title} {year}"
    search_url = 'https://en.wikipedia.org/w/api.php'
    sresp = http_get(search_url, params={
        'action': 'query', 'list': 'search', 'srsearch': q, 'format': 'json', 'srlimit': 1, 'utf8': 1
    })
    if not sresp:
        return None
    sdata = sresp.json()
    hits = sdata.get('query', {}).get('search', [])
    if not hits:
        return None
    page_title = hits[0].get('title')

    # 2) Get wikitext
    rev_resp = http_get(search_url, params={
        'action': 'query', 'prop': 'revisions', 'rvprop': 'content', 'rvslots': '*', 'titles': page_title, 'format': 'json', 'utf8': 1
    })
    if not rev_resp:
        return None
    rev = rev_resp.json()
    pages = rev.get('query', {}).get('pages', {})
    page = next(iter(pages.values()), None)
    if not page:
        return None
    revisions = page.get('revisions') or []
    content = ''
    if revisions:
        slots = revisions[0].get('slots') or {}
        for s in slots.values():
            if isinstance(s, dict):
                content = s.get('*') or s.get('content') or ''
                if content:
                    break

    if content:
        m = re.search(r"^\|\s*(?:director|directed by|directors?)\s*=\s*(.+)$", content, flags=re.IGNORECASE | re.MULTILINE)
        if m:
            raw = m.group(1).strip()
            clean = clean_wikitext(raw)
            return clean or None

    # 3) fallback to extract
    ext_resp = http_get(search_url, params={
        'action': 'query', 'prop': 'extracts', 'explaintext': 1, 'titles': page_title, 'format': 'json', 'utf8': 1, 'exintro': 1
    })
    if ext_resp:
        ed = ext_resp.json()
        pgs = ed.get('query', {}).get('pages', {})
        p = next(iter(pgs.values()), {})
        text = p.get('extract', '')
        if text:
            m2 = re.search(r"Directed by\s*[:\-]?\s*(.+)", text, flags=re.IGNORECASE)
            if m2:
                cand = m2.group(1).split('\n', 1)[0].split('.', 1)[0].strip()
                return cand or None

    return None


def clean_wikitext(s: str) -> str:
    # remove templates {{...}} (simple, non-recursive)
    s = re.sub(r"\{\{[^\}]*\}\}", '', s)
    # replace [[a|b]] or [[b]] with b
    def repl_link(m):
        parts = m.group(1).split('|')
        return parts[-1]
    s = re.sub(r"\[\[([^\]]+)\]\]", repl_link, s)
    # remove refs
    s = re.sub(r"<ref[^>]*>.*?<\/ref>", '', s, flags=re.DOTALL)
    s = re.sub(r"<[^>]+>", '', s)
    # collapse whitespace and newlines
    s = re.sub(r"[\r\n]+", ", ", s)
    s = re.sub(r"\s*,\s*", ", ", s)
    s = s.strip(' \t\n,;')
    return s


def main(argv=None):
    p = argparse.ArgumentParser()
    p.add_argument('--dry-run', action='store_true')
    p.add_argument('--limit', type=int, default=None)
    p.add_argument('--offset', type=int, default=0)
    p.add_argument('--force', action='store_true')
    p.add_argument('--sleep', type=float, default=1.0)
    args = p.parse_args(argv)

    try:
        cfg = load_db_config()
    except Exception as e:
        print(f"Failed to load DB config: {e}", file=sys.stderr)
        sys.exit(2)

    try:
        conn = connect_db(cfg)
    except Exception as e:
        print(f"Failed to connect to DB: {e}", file=sys.stderr)
        sys.exit(2)

    rows = fetch_rows(conn, args.limit, args.offset)
    print(f"Found {len(rows)} rows to process")

    for r in rows:
        dkey = r.get('dkey')
        title = (r.get('title') or '').strip()
        year = (r.get('year') or '').strip()
        existing = (r.get('director') or '').strip()

        if existing and not args.force:
            print(f"[{dkey}] '{title}' — director already set ('{existing}'), skipping")
            continue

        print(f"[{dkey}] Processing: {title}" + (f" ({year})" if year else ""))
        director = fetch_director_from_wikipedia(title, year)
        if director is None:
            print("  → director not found")
        else:
            print(f"  → director: {director}")
            if not args.dry_run:
                try:
                    update_director(conn, dkey, director)
                except Exception as e:
                    print(f"  ! Failed to update DB: {e}", file=sys.stderr)

        time.sleep(args.sleep)

    conn.close()


if __name__ == '__main__':
    main()
