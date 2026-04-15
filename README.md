# Geo Challenge

Geo Challenge is a multiplayer geography trivia website built for a LAMP deployment on the CISE server. It uses PHP sessions for player state, MySQL for the data model, Bootstrap and jQuery on the frontend, and JSON/SSE endpoints for live gameplay updates.

## Deployment Target

- Project folder: `group/`
- Entry page: `http://server/~username/cis4930/group/index.php`
- GitHub remote: `https://github.com/RunningUpTheHill/Geo-Challenge.git`

## Remote MySQL Setup

### 1. Create `db_config.php`

Copy the template in [`db_config.php`](./db_config.php) and replace the placeholder values with your remote CISE MySQL credentials:

```php
<?php
return array(
    'host' => 'mysql.cise.ufl.edu',
    'port' => '3306',
    'dbname' => 'YOUR_DB_NAME',
    'username' => 'YOUR_USERNAME',
    'password' => 'YOUR_PASSWORD',
    'charset' => 'utf8mb4',
);
```

Do not commit real credentials to GitHub.

### 2. Create and seed the database

The schema file creates the database and selects it with `CREATE DATABASE` and `USE`, so run both files against the remote server:

```bash
mysql -h mysql.cise.ufl.edu -P 3306 -u YOUR_USERNAME -p < db/schema.sql
mysql -h mysql.cise.ufl.edu -P 3306 -u YOUR_USERNAME -p < db/seed.sql
```

If your instructor expects a different database name, update `dbname` in `db_config.php` and the `CREATE DATABASE` / `USE` lines in `db/schema.sql` to match.

## Project Features

- Create a multiplayer trivia room with a 6-character session code.
- Join an existing room and keep player identity on the server with PHP sessions.
- Start and end games from the host account only.
- Play timed geography rounds with live score updates through Server-Sent Events.
- View final rankings based on score and total answer time.

## Rubric Checklist

- **W3C-valid HTML/CSS:** Each page uses semantic HTML with an external stylesheet and responsive layout patterns.
- **JavaScript:** Client behavior is powered by JavaScript with jQuery for form submission and UI updates.
- **PHP sessions:** Active player identity is stored in `$_SESSION` and used to protect gameplay actions.
- **MySQL database:** The app uses one-to-many relationships (`sessions -> players`, `sessions -> answers`) and join queries during gameplay.
- **Libraries/frameworks:** Bootstrap 5 and jQuery are integrated into the interface.
- **XML/JSON API requirement:** The site exposes internal JSON GET endpoints such as `session_status.php?code=ABC123` and `session_results.php?code=ABC123`.
- **GitHub version control:** The project is tracked in the GitHub repository listed above.

## How to Play

1. Open the homepage and create a game with your name.
2. Share the session code with the other players.
3. Join from a second browser or device using the same code.
4. Start the game as the host.
5. Answer each timed question before the progress bar runs out.
6. Review the final results table and podium after the game ends.
