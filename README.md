# Geo Challenge

Link:https://cise.ufl.edu/~tianzhong.j/cis4930/group/https://cise.ufl.edu/~tianzhong.j/cis4930/group/

A multiplayer geography trivia platform built with PHP, MySQL, JavaScript, Bootstrap, and jQuery.

## Setup

### 1. Install MAMP
Download the free version from [mamp.info/en/downloads](https://www.mamp.info/en/downloads/) and install it.

### 2. Configure MAMP
1. Open MAMP and click **Start**
2. Go to **Preferences → Web Server** and set the document root to the path of this project
3. Confirm both Apache and MySQL indicators are green

### 3. Enable mod_rewrite (one-time)

**macOS:**
Run this in your terminal, then restart MAMP (Stop → Start):
```bash
sed -i '' 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /Applications/MAMP/conf/apache/httpd.conf
```

**Windows:**
Open `C:\MAMP\conf\apache\httpd.conf` in a text editor, find the line `#LoadModule rewrite_module`, remove the `#` to uncomment it, save, and restart MAMP (Stop → Start).

### 4. Create the database

**macOS:**
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot --host=127.0.0.1 --port=8889 < db/schema.sql
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot --host=127.0.0.1 --port=8889 < db/seed.sql
```

**Windows:**
```bash
C:\MAMP\bin\mysql\bin\mysql -u root -proot --host=127.0.0.1 --port=3306 < db/schema.sql
C:\MAMP\bin\mysql\bin\mysql -u root -proot --host=127.0.0.1 --port=3306 < db/seed.sql
```
> Note: MAMP on Windows uses port **3306** for MySQL by default (not 8889). Check your MAMP preferences to confirm.

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
- **Frontend:** HTML, CSS, JavaScript, Bootstrap 5, jQuery
- **Real-time:** Server-Sent Events (SSE)
- **Server:** Apache via MAMP

## Admin Panel
Visit **http://localhost:8888/admin** to manage questions.
- Username: `admin`
- Password: `geochallenge`
