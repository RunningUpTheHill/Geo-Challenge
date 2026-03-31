function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function persistPlayerSession(data, name) {
    sessionStorage.setItem('gc_player_id', String(data.player_id));
    sessionStorage.setItem('gc_player_name', name);

    localStorage.removeItem('gc_player_id');
    localStorage.removeItem('gc_player_name');
    localStorage.removeItem('gc_is_host');
}

async function createGame() {
    const name   = document.getElementById('create-name').value.trim();
    const errEl  = document.getElementById('create-error');
    errEl.textContent = '';

    if (name.length < 2) {
        errEl.textContent = 'Name must be at least 2 characters.';
        return;
    }

    const btn = document.getElementById('create-btn');
    btn.disabled = true;
    btn.textContent = 'Creating…';

    try {
        const res  = await fetch('/api/session/create', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ player_name: name }),
        });
        const data = await res.json();

        if (!res.ok) {
            errEl.textContent = data.error || 'Error creating game.';
            btn.disabled = false;
            btn.textContent = 'Create Game';
            return;
        }

        persistPlayerSession(data, name);
        window.location.href = '/lobby/' + data.code;

    } catch (e) {
        errEl.textContent = 'Network error — please try again.';
        btn.disabled = false;
        btn.textContent = 'Create Game';
    }
}

async function joinGame() {
    const name   = document.getElementById('join-name').value.trim();
    const code   = document.getElementById('join-code').value.trim().toUpperCase();
    const errEl  = document.getElementById('join-error');
    errEl.textContent = '';

    if (name.length < 2) { errEl.textContent = 'Name must be at least 2 characters.'; return; }
    if (code.length !== 6) { errEl.textContent = 'Code must be exactly 6 characters.'; return; }

    const btn = document.getElementById('join-btn');
    btn.disabled = true;
    btn.textContent = 'Joining…';

    try {
        const res  = await fetch('/api/session/join', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ player_name: name, code }),
        });
        const data = await res.json();

        if (!res.ok) {
            errEl.textContent = data.error || 'Error joining game.';
            btn.disabled = false;
            btn.textContent = 'Join Game';
            return;
        }

        persistPlayerSession(data, name);
        window.location.href = '/lobby/' + data.code;

    } catch (e) {
        errEl.textContent = 'Network error — please try again.';
        btn.disabled = false;
        btn.textContent = 'Join Game';
    }
}

document.getElementById('create-btn').addEventListener('click', createGame);
document.getElementById('join-btn').addEventListener('click', joinGame);

// Enter key support
document.getElementById('create-name').addEventListener('keydown', e => {
    if (e.key === 'Enter') createGame();
});
document.getElementById('join-code').addEventListener('keydown', e => {
    if (e.key === 'Enter') joinGame();
});
document.getElementById('join-name').addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('join-code').focus();
});

// Force uppercase on code input
document.getElementById('join-code').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
});
