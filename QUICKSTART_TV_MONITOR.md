# Quick Start Guide: LG TV Price Monitor

This guide helps you quickly set up the TV price monitoring script to alert you about price drops on LG C5 and G5 TVs (77" or larger).

## What It Does

The script monitors prices for:
- LG OLED77C5 (77" C5)
- LG OLED83C5 (83" C5)  
- LG OLED77G5 (77" G5)
- LG OLED83G5 (83" G5)

From these retailers:
- Best Buy
- Amazon
- LG USA

When a price drops, it sends an email alert to **greg@jmarie.net** with:
- Which TV model dropped in price
- Old price vs new price
- Dollar amount and percentage saved
- Link to purchase

## Setup Instructions

### 1. Install Python Dependencies

```bash
cd /path/to/realmsofsilver-dvds
pip3 install -r scripts/requirements.txt
```

### 2. Test the Script

Test without sending emails:

```bash
python3 scripts/monitor_tv_prices.py --dry-run
```

Test with email notification (even if no price drop):

```bash
python3 scripts/monitor_tv_prices.py --dry-run --force-notify
```

### 3. Run Manually

To check prices and send alerts if prices have dropped:

```bash
python3 scripts/monitor_tv_prices.py
```

### 4. Set Up Automated Monitoring (Recommended)

Add to your crontab to check automatically:

```bash
# Edit crontab
crontab -e

# Add this line to check every 6 hours:
0 */6 * * * cd /path/to/realmsofsilver-dvds && python3 scripts/monitor_tv_prices.py >> /tmp/tv_monitor.log 2>&1
```

**Important:** Replace `/path/to/realmsofsilver-dvds` with your actual path!

## Email Configuration

### Default Setup

By default, emails are sent to: **greg@jmarie.net**

The script tries to use a local SMTP server. If you don't have one:

1. The script will show you the email content in the console
2. You can configure an external SMTP service (Gmail, SendGrid, etc.)

### To Use Gmail SMTP

Edit `scripts/monitor_tv_prices.py` and modify the `send_email_alert()` function:

```python
# Around line 450, replace the SMTP section with:
smtp_server = "smtp.gmail.com"
smtp_port = 587
smtp_username = "your.email@gmail.com"
smtp_password = "your-app-password"  # Generate in Gmail settings

with smtplib.SMTP(smtp_server, smtp_port) as server:
    server.starttls()
    server.login(smtp_username, smtp_password)
    server.send_message(msg)
```

**Note:** You'll need to generate an "App Password" in your Gmail account settings.

## How It Works

1. **First Run**: Creates a price history file (`scripts/tv_price_history.json`) and stores current prices
2. **Subsequent Runs**: 
   - Fetches new prices
   - Compares to previous prices
   - Detects drops
   - Sends alerts for any price decreases
   - Updates history

## Viewing Logs

If you set up cron with logging:

```bash
# View recent logs
tail -50 /tmp/tv_monitor.log

# View in real-time
tail -f /tmp/tv_monitor.log
```

## Troubleshooting

### "No prices found"

This is normal if:
- You're in a sandboxed environment (no internet)
- Websites have changed their structure
- You're being rate-limited

**Solution:** When run in a real environment with internet access, the script will work.

### "Email not sent"

If you see "Local SMTP not available":
- The script still works for price tracking
- Email content is shown in console/logs
- Configure external SMTP for actual email delivery

### Testing Everything

Full test sequence:

```bash
# 1. Test script syntax
python3 -m py_compile scripts/monitor_tv_prices.py

# 2. Test with dry run
python3 scripts/monitor_tv_prices.py --dry-run

# 3. Test email formatting
python3 scripts/monitor_tv_prices.py --dry-run --force-notify

# 4. Run for real (first time won't send alerts)
python3 scripts/monitor_tv_prices.py

# 5. Run again to test price comparison
python3 scripts/monitor_tv_prices.py
```

## Customization

### Change Email Address

```bash
python3 scripts/monitor_tv_prices.py --email your.email@example.com
```

### Check Different Models

Edit `scripts/monitor_tv_prices.py` and modify the `TV_MODELS` list around line 28.

### Add More Retailers

See the "Contributing" section in `scripts/README_TV_MONITOR.md`.

## Recommended Schedule

- **Every 6 hours**: Good balance between freshness and not overwhelming
- **Twice daily**: 8 AM and 8 PM to catch deals
- **Daily**: Once a day if less urgent

## Files Created

- `scripts/monitor_tv_prices.py` - Main script
- `scripts/tv_price_history.json` - Price tracking data (auto-created)
- `/tmp/tv_monitor.log` - Execution logs (if using cron)

## Support

For detailed documentation, see: `scripts/README_TV_MONITOR.md`

For issues or questions, check the GitHub repository.

---

**That's it! You're all set to monitor TV prices.** 🎉

The script will automatically email greg@jmarie.net when it detects a price drop on any LG C5 or G5 TV (77" or larger).
