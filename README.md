# Geo Challenge

Link: https://cise.ufl.edu/~tianzhong.j/cis4930/group/

Geo Challenge is a multiplayer geography trivia web app deployed on the CISE LAMP stack. Players create or join a room with a 6-character code, wait in a live lobby, sync into a shared ready countdown, answer timed multiple-choice questions with images, see a round leaderboard after each question, and finish on a final results page with a podium and ranked standings. Scoring rewards both correctness and speed, with total response time used as a tiebreaker.

## Live Hosting

- Live app: `https://www.cise.ufl.edu/~tianzhong.j/cis4930/group/`
- Workspace root: `/cise/homes/tianzhong.j/cis4930/`
- App source folder: `/cise/homes/tianzhong.j/cis4930/group`
- Deployed app folder: `/cise/homes/tianzhong.j/public_html/cis4930/group`
- GitHub repository: `https://github.com/RunningUpTheHill/Geo-Challenge.git`

## Current Functionality

### Home Page

- Create a new game room with a player name and selected question count
- Join an existing game room with a player name and 6-character session code
- Persist player access in the browser so returning players stay attached to the correct room

### Lobby

- Show the room code with a copy button for sharing
- Keep the player list updated live while the room is waiting
- Show host only controls for starting the game
- Prevent non-host players from starting the session

### Game Flow

- Run a synchronized ready phase before the first question begins
- Show timed multiple choice questions with one image per question
- Lock answers once submitted and move everyone through a shared round state
- Show a round leaderboard between questions
- Allow the host to end the game early for all players

### Results

- Show a final podium for the top players
- Show a ranked results table with correct answers, score, and total response time
- Provide a copyable results link and a play-again path back to the home page

### Live Updates

- Use internal JSON endpoints for state fetches and actions
- Use `stream.php` Server-Sent Events for live lobby and gameplay updates

## Architecture / Stack

- Apache with PHP 7.4 on the CISE server
- MySQL for sessions, players, questions, session questions, and answers
- Bootstrap 5 and jQuery on the frontend
- PHP sessions for standard web-session behavior and flash messaging
- Per-player auth tokens to protect gameplay requests after create/join
- JSON APIs and Server Sent Events for real-time synchronization

## Database Setup

The app expects remote MySQL credentials in a `db_config.php` file. At runtime it looks for:

- `group/db_config.php`
- `/cise/homes/tianzhong.j/cis4930/db_config.php`

Example config:

```php
<?php
return [
    'host' => 'mysql.cise.ufl.edu',
    'port' => '3306',
    'dbname' => 'YOUR_DB_NAME',
    'username' => 'YOUR_USERNAME',
    'password' => 'YOUR_PASSWORD',
    'charset' => 'utf8mb4',
];
```

Do not commit real credentials.

Initialize the database with:

```bash
mysql -h mysql.cise.ufl.edu -P 3306 -u YOUR_USERNAME -p < db/schema.sql
mysql -h mysql.cise.ufl.edu -P 3306 -u YOUR_USERNAME -p < db/seed.sql
```

Notes:

- `db/schema.sql` creates the base tables
- `db/seed.sql` loads the built-in question bank
- On app startup, PHP also runs schema/bootstrap checks from `ensure_group_schema()` to add or update required columns and indexes for the current project version

## Question Images

- Flag questions use `flagcdn.com` image URLs directly
- Non-flag questions use curated Wikimedia REST lookups tied to each question
- Resolved Wikimedia image URLs are cached in MySQL in `questions.image_url`
- Local SVG artwork in `public/img/questions/` is fallback-only if no acceptable Wikimedia image is available quickly

## Key Routes / Endpoints

### User-Facing Pages

- `index.php` - home page for create/join
- `lobby.php` - pregame waiting room
- `game.php` - live gameplay screen
- `results.php` - final standings page

### JSON Endpoints

- `create_session.php`
- `join_session.php`
- `start_game.php`
- `game_ready.php`
- `submit_answer.php`
- `end_game.php`
- `session_status.php`
- `session_results.php`

### SSE Endpoint

- `stream.php`
