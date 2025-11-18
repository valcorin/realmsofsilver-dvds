#!/usr/bin/env python3
"""
Monitor LG C5 and G5 TV prices (77" or larger) from various retailers.
Sends email alerts when price drops are detected.

Usage:
    python scripts/monitor_tv_prices.py [--dry-run] [--email EMAIL] [--force-notify]

Options:
    --dry-run         Don't send emails, just show what would be sent
    --email EMAIL     Email address for alerts (default: greg@jmarie.net)
    --force-notify    Send notification even if price hasn't dropped
"""

import argparse
import json
import os
import re
import smtplib
import sys
import time
from datetime import datetime
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from typing import Dict, List, Optional, Tuple
from pathlib import Path

import requests
from bs4 import BeautifulSoup
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry


# Configuration
SCRIPT_DIR = Path(__file__).parent
PRICE_HISTORY_FILE = SCRIPT_DIR / 'tv_price_history.json'
USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'

# Configure a requests Session with retries to handle transient network issues
SESSION = requests.Session()
RETRY_STRATEGY = Retry(
    total=3,
    backoff_factor=1,
    status_forcelist=[429, 500, 502, 503, 504],
    allowed_methods=frozenset(['GET', 'POST'])
)
ADAPTER = HTTPAdapter(max_retries=RETRY_STRATEGY)
SESSION.mount('https://', ADAPTER)
SESSION.mount('http://', ADAPTER)

# TV models to monitor
TV_MODELS = [
    'LG OLED77C5',
    'LG OLED83C5',
    'LG OLED77G5',
    'LG OLED83G5',
    'LG C5 77',
    'LG C5 83',
    'LG G5 77',
    'LG G5 83',
]

# Retailer configurations
RETAILERS = {
    'bestbuy': {
        'name': 'Best Buy',
        'search_urls': [
            'https://www.bestbuy.com/site/searchpage.jsp?st=LG+OLED77C5',
            'https://www.bestbuy.com/site/searchpage.jsp?st=LG+OLED83C5',
            'https://www.bestbuy.com/site/searchpage.jsp?st=LG+OLED77G5',
            'https://www.bestbuy.com/site/searchpage.jsp?st=LG+OLED83G5',
        ],
    },
    'amazon': {
        'name': 'Amazon',
        'search_urls': [
            'https://www.amazon.com/s?k=LG+OLED77C5',
            'https://www.amazon.com/s?k=LG+OLED83C5',
            'https://www.amazon.com/s?k=LG+OLED77G5',
            'https://www.amazon.com/s?k=LG+OLED83G5',
        ],
    },
    'lgusa': {
        'name': 'LG USA',
        'search_urls': [
            'https://www.lg.com/us/tvs?filters=size:77,series:c5',
            'https://www.lg.com/us/tvs?filters=size:83,series:c5',
            'https://www.lg.com/us/tvs?filters=size:77,series:g5',
            'https://www.lg.com/us/tvs?filters=size:83,series:g5',
        ],
    },
}


def load_price_history() -> Dict:
    """Load price history from JSON file."""
    if PRICE_HISTORY_FILE.exists():
        try:
            with open(PRICE_HISTORY_FILE, 'r') as f:
                return json.load(f)
        except Exception as e:
            print(f"Error loading price history: {e}", file=sys.stderr)
    return {}


def save_price_history(history: Dict):
    """Save price history to JSON file."""
    try:
        with open(PRICE_HISTORY_FILE, 'w') as f:
            json.dump(history, f, indent=2)
    except Exception as e:
        print(f"Error saving price history: {e}", file=sys.stderr)


def http_get(url: str, timeout: int = 30) -> Optional[requests.Response]:
    """Make HTTP GET request with a session that retries on transient failures.

    Uses a shared `SESSION` configured with a urllib3 `Retry` strategy. Default
    timeout increased to 30 seconds to reduce false timeouts for slow hosts.
    """
    headers = {
        'User-Agent': USER_AGENT,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.5',
        'Accept-Encoding': 'gzip, deflate',
        'DNT': '1',
        'Connection': 'keep-alive',
        'Upgrade-Insecure-Requests': '1',
    }

    try:
        response = SESSION.get(url, headers=headers, timeout=timeout)
        response.raise_for_status()
        return response
    except requests.Timeout as e:
        print(f"HTTP request timed out for {url}: {e}", file=sys.stderr)
    except requests.RequestException as e:
        print(f"HTTP request failed for {url}: {e}", file=sys.stderr)
    return None


def extract_price_from_text(text: str) -> Optional[float]:
    """Extract price from text string."""
    # Match patterns like $1,999.99, $1999, etc.
    patterns = [
        r'\$\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)',
        r'(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)\s*(?:USD|dollars?)',
    ]
    
    for pattern in patterns:
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            price_str = match.group(1).replace(',', '')
            try:
                return float(price_str)
            except ValueError:
                continue
    return None


def scrape_bestbuy(url: str) -> List[Dict]:
    """Scrape TV prices from Best Buy."""
    results = []
    response = http_get(url)
    if not response:
        return results
    
    try:
        soup = BeautifulSoup(response.content, 'html.parser')
        # Find product listings
        products = soup.find_all('li', class_=re.compile(r'sku-item'))
        
        for product in products[:5]:  # Check first 5 results
            try:
                # Extract title
                title_elem = product.find('h4', class_='sku-title')
                if not title_elem:
                    continue
                title = title_elem.get_text(strip=True)
                
                # Check if it's a relevant TV model
                if not any(model.lower() in title.lower() for model in TV_MODELS):
                    continue
                
                # Extract price
                price_elem = product.find('span', {'aria-label': re.compile(r'Your price')})
                if not price_elem:
                    price_elem = product.find('span', class_=re.compile(r'priceView-hero-price'))
                
                if price_elem:
                    price = extract_price_from_text(price_elem.get_text())
                    if price and price > 1000:  # Sanity check
                        results.append({
                            'retailer': 'Best Buy',
                            'title': title,
                            'price': price,
                            'url': url,
                        })
            except Exception as e:
                print(f"Error parsing Best Buy product: {e}", file=sys.stderr)
                continue
    except Exception as e:
        print(f"Error scraping Best Buy: {e}", file=sys.stderr)
    
    return results


def scrape_amazon(url: str) -> List[Dict]:
    """Scrape TV prices from Amazon."""
    results = []
    response = http_get(url)
    if not response:
        return results
    
    try:
        soup = BeautifulSoup(response.content, 'html.parser')
        # Find product listings
        products = soup.find_all('div', {'data-component-type': 's-search-result'})
        
        for product in products[:5]:  # Check first 5 results
            try:
                # Extract title
                title_elem = product.find('h2', class_='s-line-clamp-2')
                if not title_elem:
                    continue
                title = title_elem.get_text(strip=True)
                
                # Check if it's a relevant TV model
                if not any(model.lower() in title.lower() for model in TV_MODELS):
                    continue
                
                # Extract price
                price_elem = product.find('span', class_='a-price-whole')
                if price_elem:
                    price = extract_price_from_text(price_elem.get_text())
                    if price and price > 1000:  # Sanity check
                        results.append({
                            'retailer': 'Amazon',
                            'title': title,
                            'price': price,
                            'url': url,
                        })
            except Exception as e:
                print(f"Error parsing Amazon product: {e}", file=sys.stderr)
                continue
    except Exception as e:
        print(f"Error scraping Amazon: {e}", file=sys.stderr)
    
    return results


def scrape_lg_usa(url: str) -> List[Dict]:
    """Scrape TV prices from LG USA website."""
    results = []
    response = http_get(url)
    if not response:
        return results
    
    try:
        soup = BeautifulSoup(response.content, 'html.parser')
        # Find product listings (LG's structure may vary)
        products = soup.find_all('div', class_=re.compile(r'product-card|product-item'))
        
        for product in products[:5]:  # Check first 5 results
            try:
                # Extract title
                title_elem = product.find(['h2', 'h3', 'h4'], class_=re.compile(r'title|name'))
                if not title_elem:
                    continue
                title = title_elem.get_text(strip=True)
                
                # Extract price
                price_elem = product.find(class_=re.compile(r'price'))
                if price_elem:
                    price = extract_price_from_text(price_elem.get_text())
                    if price and price > 1000:  # Sanity check
                        results.append({
                            'retailer': 'LG USA',
                            'title': title,
                            'price': price,
                            'url': url,
                        })
            except Exception as e:
                print(f"Error parsing LG USA product: {e}", file=sys.stderr)
                continue
    except Exception as e:
        print(f"Error scraping LG USA: {e}", file=sys.stderr)
    
    return results


def scrape_all_retailers() -> List[Dict]:
    """Scrape prices from all retailers."""
    all_results = []
    
    print("Scraping Best Buy...")
    for url in RETAILERS['bestbuy']['search_urls']:
        results = scrape_bestbuy(url)
        all_results.extend(results)
        time.sleep(2)  # Be nice to servers
    
    print("Scraping Amazon...")
    for url in RETAILERS['amazon']['search_urls']:
        results = scrape_amazon(url)
        all_results.extend(results)
        time.sleep(2)
    
    print("Scraping LG USA...")
    for url in RETAILERS['lgusa']['search_urls']:
        results = scrape_lg_usa(url)
        all_results.extend(results)
        time.sleep(2)
    
    return all_results


def check_for_price_drops(current_prices: List[Dict], history: Dict) -> List[Dict]:
    """Check if any prices have dropped since last check."""
    drops = []
    timestamp = datetime.now().isoformat()
    
    for item in current_prices:
        key = f"{item['retailer']}_{item['title']}"
        
        # Update history
        if key not in history:
            history[key] = {
                'first_seen': timestamp,
                'prices': [],
            }
        
        # Add current price to history
        history[key]['prices'].append({
            'timestamp': timestamp,
            'price': item['price'],
        })
        
        # Keep only last 100 price points
        if len(history[key]['prices']) > 100:
            history[key]['prices'] = history[key]['prices'][-100:]
        
        # Check for price drop
        prices = history[key]['prices']
        if len(prices) >= 2:
            previous_price = prices[-2]['price']
            current_price = item['price']
            
            if current_price < previous_price:
                drop_amount = previous_price - current_price
                drop_percent = (drop_amount / previous_price) * 100
                
                drops.append({
                    'retailer': item['retailer'],
                    'title': item['title'],
                    'previous_price': previous_price,
                    'current_price': current_price,
                    'drop_amount': drop_amount,
                    'drop_percent': drop_percent,
                    'url': item['url'],
                })
    
    return drops


def send_email_alert(email_to: str, price_drops: List[Dict], all_prices: List[Dict], dry_run: bool = False):
    """Send email alert for price drops."""
    if not price_drops:
        print("No price drops detected, skipping email.")
        return
    
    # Create email content
    subject = f"🔔 LG TV Price Drop Alert - {len(price_drops)} deal(s) found!"
    
    # Build HTML email
    html_body = """
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .price-drop { background-color: #f9f9f9; border-left: 4px solid #4CAF50; padding: 15px; margin: 15px 0; }
            .price-drop h3 { margin-top: 0; color: #4CAF50; }
            .price { font-size: 24px; font-weight: bold; color: #4CAF50; }
            .old-price { text-decoration: line-through; color: #999; }
            .savings { color: #4CAF50; font-weight: bold; }
            .all-prices { margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🎉 LG C5/G5 TV Price Drop Alert!</h1>
        </div>
        <div class="content">
            <p>Great news! We detected price drops on LG C5/G5 TVs (77" or larger):</p>
    """
    
    for drop in price_drops:
        html_body += f"""
            <div class="price-drop">
                <h3>{drop['title']}</h3>
                <p><strong>Retailer:</strong> {drop['retailer']}</p>
                <p>
                    <span class="old-price">${drop['previous_price']:.2f}</span> 
                    → 
                    <span class="price">${drop['current_price']:.2f}</span>
                </p>
                <p class="savings">
                    💰 Save ${drop['drop_amount']:.2f} ({drop['drop_percent']:.1f}% off)
                </p>
                <p><a href="{drop['url']}" style="color: #4CAF50;">View on {drop['retailer']}</a></p>
            </div>
        """
    
    if all_prices:
        html_body += """
            <div class="all-prices">
                <h2>All Current Prices</h2>
        """
        for item in all_prices:
            html_body += f"""
                <p><strong>{item['title']}</strong> at {item['retailer']}: ${item['price']:.2f}</p>
            """
        html_body += "</div>"
    
    html_body += f"""
            <div class="footer">
                <p>This alert was generated by the LG TV Price Monitor script.</p>
                <p>Timestamp: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}</p>
            </div>
        </div>
    </body>
    </html>
    """
    
    # Create plain text version
    text_body = f"LG C5/G5 TV Price Drop Alert!\n\n"
    text_body += f"Detected {len(price_drops)} price drop(s):\n\n"
    
    for drop in price_drops:
        text_body += f"{drop['title']}\n"
        text_body += f"Retailer: {drop['retailer']}\n"
        text_body += f"Was: ${drop['previous_price']:.2f} → Now: ${drop['current_price']:.2f}\n"
        text_body += f"Save: ${drop['drop_amount']:.2f} ({drop['drop_percent']:.1f}% off)\n"
        text_body += f"URL: {drop['url']}\n\n"
    
    if dry_run:
        print("\n" + "="*60)
        print("DRY RUN - Email would be sent:")
        print(f"To: {email_to}")
        print(f"Subject: {subject}")
        print("\nText body:")
        print(text_body)
        print("="*60 + "\n")
        return
    
    try:
        # Try to send via local SMTP server
        msg = MIMEMultipart('alternative')
        msg['Subject'] = subject
        msg['From'] = 'tv-monitor@realmsofsilver.com'
        msg['To'] = email_to
        
        part1 = MIMEText(text_body, 'plain')
        part2 = MIMEText(html_body, 'html')
        
        msg.attach(part1)
        msg.attach(part2)
        
        # Try localhost SMTP first
        try:
            with smtplib.SMTP('localhost', 25, timeout=10) as server:
                server.send_message(msg)
                print(f"✓ Email sent successfully to {email_to}")
                return
        except Exception:
            pass
        
        # If localhost fails, try common mail servers
        print("Note: Local SMTP not available. Configure SMTP settings to send emails.")
        print("You can use services like SendGrid, AWS SES, or Gmail SMTP.")
        print("\nEmail content saved to console (see above).")
        
    except Exception as e:
        print(f"Error sending email: {e}", file=sys.stderr)
        print("\nEmail content (text version):")
        print(text_body)


def main(argv=None):
    """Main function."""
    parser = argparse.ArgumentParser(description='Monitor LG C5/G5 TV prices')
    parser.add_argument('--dry-run', action='store_true',
                        help="Don't send emails, just show what would be sent")
    parser.add_argument('--email', type=str, default='greg@jmarie.net',
                        help='Email address for alerts (default: greg@jmarie.net)')
    parser.add_argument('--force-notify', action='store_true',
                        help='Send notification even if price hasn\'t dropped')
    
    args = parser.parse_args(argv)
    
    print("="*60)
    print("LG C5/G5 TV Price Monitor")
    print("Monitoring models: 77\" and 83\" sizes")
    print("="*60 + "\n")
    
    # Load price history
    history = load_price_history()
    print(f"Loaded price history for {len(history)} products\n")
    
    # Scrape current prices
    print("Fetching current prices from retailers...")
    current_prices = scrape_all_retailers()
    
    if not current_prices:
        print("\n⚠️  Warning: No prices found. This could be due to:")
        print("  - Website structure changes (requires script update)")
        print("  - Network issues")
        print("  - Rate limiting by retailers")
        print("\nConsider running with --dry-run to test scraping logic.")
        return
    
    print(f"\n✓ Found {len(current_prices)} current prices")
    
    # Display current prices
    print("\nCurrent prices:")
    for item in current_prices:
        print(f"  {item['retailer']}: {item['title']} - ${item['price']:.2f}")
    
    # Check for price drops
    price_drops = check_for_price_drops(current_prices, history)
    
    # Save updated history
    save_price_history(history)
    print(f"\n✓ Price history saved to {PRICE_HISTORY_FILE}")
    
    # Send alerts if price drops detected
    if price_drops or args.force_notify:
        if args.force_notify and not price_drops:
            print("\n--force-notify enabled, sending notification anyway")
            price_drops = [{
                'retailer': item['retailer'],
                'title': item['title'],
                'previous_price': item['price'],
                'current_price': item['price'],
                'drop_amount': 0,
                'drop_percent': 0,
                'url': item['url'],
            } for item in current_prices[:1]]  # Just send first item
        
        print(f"\n🔔 Detected {len(price_drops)} price drop(s)!")
        for drop in price_drops:
            print(f"  {drop['retailer']}: {drop['title']}")
            print(f"    ${drop['previous_price']:.2f} → ${drop['current_price']:.2f} "
                  f"(Save ${drop['drop_amount']:.2f}, {drop['drop_percent']:.1f}% off)")
        
        send_email_alert(args.email, price_drops, current_prices, dry_run=args.dry_run)
    else:
        print("\n✓ No price drops detected at this time.")
    
    print("\n" + "="*60)
    print("Run this script periodically (e.g., via cron) to monitor prices.")
    print("Suggested cron schedule: 0 */6 * * * (every 6 hours)")
    print("="*60)


if __name__ == '__main__':
    main()
