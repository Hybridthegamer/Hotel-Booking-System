# Grand Royale Hotel — Booking System with Intelligent Reservation Queue Management

> **Final Year Project** | Amadi Benjamin  
> *Design and Implementation of a Hotel Booking System with Intelligent Reservation Queue Management*

---

## Project Overview

This is a fully automated, web-based hotel booking and reservation system built using **PHP** and **MySQL**, implementing three core intelligent features:

1. **Intelligent Reservation Queue** — fair room allocation when demand exceeds supply
2. **Smart Temporary Reservation Expiry** — timed room hold to prevent hoarding
3. **Trust Scoring System** — behavioural reputation tracking for priority queueing

The system addresses the weaknesses of manual and semi-automated systems (double bookings, lack of real-time updates, no security) identified in Chapters 1–3, replacing them with a fully automated, SSADM-designed platform.

---

## System Architecture

```
Customer → Web Booking Interface (HTML/CSS/JS)
        → Application Server (PHP Reservation Engine)
        → Intelligent Queue Manager / Reservation Expiry Module / Trust Scoring System
        → MySQL Database
```

---

## Tool Stack & Rationale

| Technology | Purpose | Why It Was Chosen |
|---|---|---|
| **PHP 8+** | Backend logic, session management, business rules | Specified in project objectives; server-side scripting with rich database support; open-source and widely deployed |
| **MySQL** | Relational database (rooms, bookings, queue, trust logs) | Specified in project objectives; ACID-compliant; supports complex JOINs needed for queue priority and booking history |
| **HTML5 / CSS3** | Structure and styling of web pages | Specified in objectives; provides semantic structure for accessible, standards-compliant UI |
| **Bootstrap 5** | Responsive grid and UI components | Industry-standard CSS framework; ensures mobile-first responsive design across all screen sizes |
| **JavaScript (Vanilla)** | Client-side interactivity — countdown timer, card formatting, filter buttons | Lightweight, no build step required; handles real-time countdown for the 15-minute room hold |
| **SSADM** | System design methodology | Adopted as stated in Chapter 3; structured, documentation-driven approach suitable for academic FYP |

---

## Core Features

### Guest-Facing
- User registration with Nigerian state selection
- Secure login with session management
- Real-time room availability search by date range and type
- Room browsing with amenity display (Commercial, Business, Executive, Double, Suite)
- **15-minute temporary room hold** — room is locked while guest completes payment
- Online payment simulation (card, bank transfer, cash on arrival)
- Instant booking confirmation with reference number
- Personal dashboard showing bookings, trust score, and queue positions
- Booking cancellation with trust score impact preview
- Reservation queue management — join/leave queue for any room type

### Admin-Facing
- Admin dashboard with live stats: occupancy rate, revenue by type, recent bookings
- Booking management — view, search, filter, update status (confirm → check-in → check-out)
- Room management — add, edit, delete rooms via modal form
- Guest management — view trust scores, booking history, suspend/activate accounts
- Reports — revenue by type, queue analytics, daily revenue bar chart, top guests

### Intelligent Queue System
- Users auto-placed in queue when no rooms are available for their dates/type
- **Priority Score** = `(100 - trust_score) × 0.5` — lower score = served first
- FIFO tiebreaker for equal priorities
- 24-hour queue expiry — prevents stale entries
- Real-time position display on dashboard

### Trust Scoring Algorithm
| Event | Score Change |
|---|---|
| Booking confirmed & paid | +2 |
| Stay completed | +5 |
| Extended stay (5+ nights) | +8 |
| Booking cancelled | −3 |
| Payment failed (hold expired) | −5 |
| No-show | −10 |

Score is clamped to [0, 100]. Users with score ≥ 80 receive priority queue placement.

---

## Database Schema

```
users              — guest accounts, trust score counters
rooms              — room inventory with status
bookings           — reservation records with payment
temp_reservations  — 15-minute room holds with expiry
reservation_queue  — priority queue entries
trust_score_log    — audit trail of score changes
payments           — transaction records
admin_logs         — admin action audit trail
```

---

## System Requirements

### Server Requirements
| Component | Minimum |
|---|---|
| PHP | 8.0 or higher |
| MySQL | 5.7+ or MariaDB 10.3+ |
| Web Server | Apache 2.4+ or Nginx 1.18+ |
| PHP Extensions | `mysqli`, `session`, `mbstring`, `openssl` |

### Client Requirements
| Component | Requirement |
|---|---|
| Browser | Any modern browser (Chrome 90+, Firefox 88+, Edge 90+, Safari 14+) |
| Internet | Required (CDN assets: Bootstrap, Bootstrap Icons, Google Fonts) |
| Screen | Responsive — works on mobile (320px) through desktop (1920px) |

---

## Setup Instructions

### Option A — XAMPP / WAMP (Local Development)

**1. Clone / Copy Files**
```bash
git clone https://github.com/hybridthegamer/hotel-booking-system.git
# Copy the project folder into:
#   XAMPP: C:\xampp\htdocs\Hotel-Booking-System
#   WAMP:  C:\wamp64\www\Hotel-Booking-System
```

**2. Create the Database**
```sql
-- Open phpMyAdmin → New → Create database named: hotel_booking
-- Import the file: database/hotel_booking.sql
```
Or via terminal:
```bash
mysql -u root -p < database/hotel_booking.sql
```

**3. Configure Database Connection**

Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your MySQL username
define('DB_PASS', '');           // your MySQL password
define('DB_NAME', 'hotel_booking');
define('SITE_URL', 'http://localhost/Hotel-Booking-System');
```

**4. Start Apache and MySQL** in XAMPP/WAMP control panel.

**5. Open in browser:**
```
http://localhost/Hotel-Booking-System/index.php
```

---

### Option B — Linux / Ubuntu Server (Production)

**1. Install the stack**
```bash
sudo apt update
sudo apt install apache2 php8.1 php8.1-mysqli php8.1-mbstring mysql-server -y
sudo systemctl start apache2 mysql
```

**2. Clone project**
```bash
cd /var/www/html
sudo git clone https://github.com/hybridthegamer/hotel-booking-system.git Hotel-Booking-System
sudo chown -R www-data:www-data Hotel-Booking-System
```

**3. Set up database**
```bash
sudo mysql -u root -p
```
```sql
CREATE DATABASE hotel_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hoteluser'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL PRIVILEGES ON hotel_booking.* TO 'hoteluser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
mysql -u hoteluser -p hotel_booking < /var/www/html/Hotel-Booking-System/database/hotel_booking.sql
```

**4. Update `includes/config.php`** with your database credentials and site URL.

**5. Enable Apache mod_rewrite:**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## Default Login Credentials

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `Admin@1234` |
| Guest | Register at `/register.php` | (your choice) |

> **Security note:** Change the admin password immediately after first login in a production environment.

---

## Project File Structure

```
Hotel-Booking-System/
├── index.php               # Homepage with hero + room search
├── login.php               # Authentication
├── register.php            # Guest registration
├── logout.php              # Session destroy
├── rooms.php               # Room listing with real-time availability
├── book.php                # Booking form
├── payment.php             # Payment page with countdown timer
├── confirmation.php        # Booking success page
├── dashboard.php           # Guest dashboard
├── my-bookings.php         # Booking history
├── cancel-booking.php      # Cancellation with trust impact
├── queue-status.php        # Queue management UI
│
├── admin/
│   ├── index.php           # Admin dashboard with stats
│   ├── bookings.php        # Manage all bookings
│   ├── rooms.php           # Add/edit/delete rooms
│   ├── users.php           # Manage guest accounts
│   ├── reports.php         # Revenue & analytics
│   └── partials/
│       └── sidebar.php     # Admin navigation
│
├── includes/
│   ├── config.php          # DB connection, constants, helpers
│   ├── functions.php       # Shared query functions
│   ├── header.php          # HTML head + navbar
│   └── footer.php          # Footer + scripts
│
├── queue/
│   ├── QueueManager.php    # Priority queue logic
│   ├── ReservationExpiry.php # 15-min room hold & expiry
│   └── TrustScore.php      # Trust score recording & retrieval
│
├── css/
│   └── style.css           # Custom styles
├── js/
│   └── main.js             # Client-side logic + countdown
│
└── database/
    └── hotel_booking.sql   # Full schema + seed data
```

---

## Algorithm Implementation (Chapter 3 Reference)

### Booking Flow (Flowchart — Figure 3.3 equivalent)
```
START
  → Customer visits site
  → Customer registered? NO → Register
  → YES → Login
  → Search available rooms (by date, type)
  → Room available?
      NO  → Join Reservation Queue
      YES → Select room → Room HELD (15 min timer starts)
              → Payment completed within 15 min?
                  NO  → Hold expires, room released, trust score −5
                  YES → Booking confirmed, room status = occupied, trust score +2
STOP
```

### Queue Priority Algorithm
```
priority_score = (100 - user_trust_score) * QUEUE_PRIORITY_TRUST_WEIGHT
# Lower score = higher priority
# Tiebreaker: earlier queue join time
```

---

## Chapter 4 & 5 Notes

This implementation provides the working system for Chapter 4 (System Implementation) to reference:
- Each PHP file corresponds to a functional module described in Chapter 3
- The SQL schema directly maps to Tables 3.1–3.3 (logical and physical data models)
- Trust scoring, queue management, and expiry are separate classes in `/queue/` for clean architectural separation
- All input is sanitised against XSS and SQL injection (prepared statements throughout)

For Chapter 5 (Testing & Evaluation), test cases should cover:
- Registration and login validation
- Room availability search accuracy
- 15-minute timer expiry behaviour
- Trust score increment/decrement
- Queue position calculation
- Admin status update propagation

---

*Built with PHP 8 & MySQL | SSADM Methodology | Intelligent Reservation Queue Management*