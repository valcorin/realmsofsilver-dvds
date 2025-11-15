# LG TV Price Monitor

This script monitors prices for LG C5 and G5 OLED TVs (77" and 83" sizes) across multiple online retailers and sends email alerts when price drops are detected.

## Features

- 🔍 **Multi-Retailer Monitoring**: Checks Best Buy, Amazon, and LG USA
- 📊 **Price History Tracking**: Maintains historical price data to detect trends
- 📧 **Email Alerts**: Sends formatted email notifications for price drops
- 💰 **Smart Detection**: Calculates savings amount and percentage
- 🤖 **Automated**: Can run on a schedule via cron

## Monitored Models

- LG OLED77C5 (77" C5 Series)
- LG OLED83C5 (83" C5 Series)
- LG OLED77G5 (77" G5 Series)
- LG OLED83G5 (83" G5 Series)

## Requirements

Install dependencies:

```bash
pip install -r requirements.txt
```

Dependencies:
- `requests` - HTTP requests
- `beautifulsoup4` - HTML parsing
- `lxml` - XML/HTML parser

## Usage

### Basic Usage

Run the script to check current prices:

```bash
python scripts/monitor_tv_prices.py
```

### Command Line Options

```bash
# Dry run (don't send emails, just show what would be sent)
python scripts/monitor_tv_prices.py --dry-run

# Specify a different email address
python scripts/monitor_tv_prices.py --email your.email@example.com

# Force send notification even if no price drop
python scripts/monitor_tv_prices.py --force-notify

# Combine options
python scripts/monitor_tv_prices.py --dry-run --force-notify
```

### First Run

On the first run, the script will:
1. Create a price history file (`tv_price_history.json`)
2. Fetch current prices from all retailers
3. Store them as baseline data
4. Not send any alerts (no historical data to compare)

### Subsequent Runs

On subsequent runs, the script will:
1. Fetch current prices
2. Compare to previous prices
3. Detect any price drops
4. Send email alerts if drops are detected
5. Update price history

## Automation with Cron

To monitor prices automatically, set up a cron job:

```bash
# Edit crontab
crontab -e

# Add one of these lines:
# Check every 6 hours
0 */6 * * * cd /path/to/realmsofsilver-dvds && python3 scripts/monitor_tv_prices.py

# Check twice daily (8 AM and 8 PM)
0 8,20 * * * cd /path/to/realmsofsilver-dvds && python3 scripts/monitor_tv_prices.py

# Check daily at 9 AM
0 9 * * * cd /path/to/realmsofsilver-dvds && python3 scripts/monitor_tv_prices.py
```

### Cron with Logging

To keep logs of the monitoring:

```bash
# Check every 6 hours and log output
0 */6 * * * cd /path/to/realmsofsilver-dvds && python3 scripts/monitor_tv_prices.py >> /tmp/tv_monitor.log 2>&1
```

## Email Configuration

### Default Configuration

By default, emails are sent to: `greg@jmarie.net`

The script attempts to use a local SMTP server on port 25.

### Custom Email Setup

To use a different email service (Gmail, SendGrid, AWS SES, etc.), you'll need to modify the `send_email_alert()` function in the script to include SMTP credentials:

```python
# Example for Gmail
smtp_server = "smtp.gmail.com"
smtp_port = 587
smtp_username = "your.email@gmail.com"
smtp_password = "your-app-password"

with smtplib.SMTP(smtp_server, smtp_port) as server:
    server.starttls()
    server.login(smtp_username, smtp_password)
    server.send_message(msg)
```

### Testing Email

Test the email functionality with dry-run:

```bash
python scripts/monitor_tv_prices.py --dry-run --force-notify
```

This will show you what the email would contain without actually sending it.

## Output

The script provides detailed console output:

```
============================================================
LG C5/G5 TV Price Monitor
Monitoring models: 77" and 83" sizes
============================================================

Loaded price history for 4 products

Fetching current prices from retailers...
Scraping Best Buy...
Scraping Amazon...
Scraping LG USA...

✓ Found 6 current prices

Current prices:
  Best Buy: LG OLED77C5 - $3299.99
  Amazon: LG 77" Class C5 OLED - $3199.99
  ...

🔔 Detected 2 price drop(s)!
  Amazon: LG 77" Class C5 OLED
    $3299.99 → $3199.99 (Save $100.00, 3.0% off)
  ...

✓ Email sent successfully to greg@jmarie.net
```

## Price History

Price history is stored in `scripts/tv_price_history.json`:

```json
{
  "Best Buy_LG OLED77C5": {
    "first_seen": "2024-01-15T10:30:00",
    "prices": [
      {
        "timestamp": "2024-01-15T10:30:00",
        "price": 3299.99
      },
      {
        "timestamp": "2024-01-15T16:30:00",
        "price": 3199.99
      }
    ]
  }
}
```

The script keeps the last 100 price points for each product.

## Troubleshooting

### No Prices Found

If the script reports "No prices found", this could be due to:

1. **Website structure changes**: Retailers update their HTML frequently
   - Solution: Update the scraping logic in the script
   
2. **Rate limiting**: Too many requests in a short time
   - Solution: Increase delays between requests or run less frequently
   
3. **Network issues**: Connection problems
   - Solution: Check your internet connection
   
4. **Bot detection**: Websites blocking automated access
   - Solution: The script uses realistic headers, but some sites may still block

### Email Not Sending

If emails aren't being sent:

1. Check if a local SMTP server is running:
   ```bash
   telnet localhost 25
   ```

2. Configure an external SMTP service (see Email Configuration section)

3. Use `--dry-run` to test without sending:
   ```bash
   python scripts/monitor_tv_prices.py --dry-run --force-notify
   ```

### Testing the Script

Test with dry-run mode first:

```bash
# Test scraping and email formatting
python scripts/monitor_tv_prices.py --dry-run

# Test with forced notification
python scripts/monitor_tv_prices.py --dry-run --force-notify
```

## Limitations

1. **Web Scraping**: Relies on website structure which can change without notice
2. **Rate Limits**: Excessive requests may trigger rate limiting
3. **Availability**: Script only detects prices when products are in stock and listed
4. **Accuracy**: Extracted prices may occasionally be incorrect due to HTML parsing

## Best Practices

1. **Don't run too frequently**: Check every 6-12 hours to avoid rate limiting
2. **Monitor the logs**: Check cron logs to ensure script is running properly
3. **Update regularly**: Retailers change their websites; script may need updates
4. **Test before automating**: Run manually with `--dry-run` first

## Privacy & Legal

- This script is for personal use only
- Respects robots.txt and uses reasonable request delays
- Uses standard User-Agent strings
- Does not bypass any security measures
- Check retailer Terms of Service before use

## Contributing

To add support for more retailers:

1. Add retailer configuration to `RETAILERS` dict
2. Implement a `scrape_<retailer>()` function
3. Add the function call in `scrape_all_retailers()`
4. Test thoroughly before committing

## License

MIT License - See repository LICENSE file

## Support

For issues or questions, please open an issue in the GitHub repository.
