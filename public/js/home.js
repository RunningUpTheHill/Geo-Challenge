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
        const numQuestions = parseInt(document.getElementById('create-questions').value, 10);
        const res  = await fetch('/api/session/create', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ player_name: name, num_questions: numQuestions }),
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

$(function () {
    $('#create-btn').on('click', createGame);
    $('#join-btn').on('click', joinGame);

    $('#create-name').on('keydown', function (e) {
        if (e.key === 'Enter') createGame();
    });
    $('#join-code').on('keydown', function (e) {
        if (e.key === 'Enter') joinGame();
    });
    $('#join-name').on('keydown', function (e) {
        if (e.key === 'Enter') $('#join-code').trigger('focus');
    });

    $('#join-code').on('input', function () {
        this.value = this.value.toUpperCase();
    });
});
