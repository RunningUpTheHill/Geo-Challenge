# Geo Challenge

A multiplayer geography trivia platform built with PHP, MySQL, and vanilla JavaScript.

## Setup

### 1. Install MAMP
Download the free version from [mamp.info/en/downloads](https://www.mamp.info/en/downloads/) and install it.

### 2. Configure MAMP
1. Open MAMP and click **Start**
2. Go to **Preferences → Web Server** and set the document root to the path of this project
3. Confirm both Apache and MySQL indicators are green

### 3. Enable mod_rewrite (one-time)
Run this in your terminal, then restart MAMP (Stop → Start):
```bash
sed -i '' 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /Applications/MAMP/conf/apache/httpd.conf
```

### 4. Create the database
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot --host=127.0.0.1 --port=8889 < db/schema.sql
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot --host=127.0.0.1 --port=8889 < db/seed.sql
```

### 5. Open the app
Visit **http://localhost:8888** in your browser.

## How to Play
1. One player creates a game and shares the 6-letter session code
2. Other players join using that code
3. The host clicks **Start Game** when everyone is ready
4. All players answer the same 10 geography questions simultaneously
5. 20 seconds per question — fastest correct answers win tiebreakers
6. Final leaderboard shown after all questions are complete

## Tech Stack
- **Backend:** PHP (vanilla, no framework)
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript (no frameworks)
- **Real-time:** Server-Sent Events (SSE)
- **Server:** Apache via MAMP
