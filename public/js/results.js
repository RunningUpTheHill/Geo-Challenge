const code = window.location.pathname.split('/').pop();

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatTime(ms) {
    if (!ms) return '—';
    return (ms / 1000).toFixed(1) + 's';
}

function renderPodium(leaderboard) {
    const podium = document.getElementById('podium');
    const top3   = leaderboard.slice(0, 3);

    if (top3.length === 0) {
        podium.innerHTML = '<p class="no-players">No players found.</p>';
        return;
    }

    // Display order: 2nd (left), 1st (centre/tallest), 3rd (right)
    const displayOrder = [top3[1], top3[0], top3[2]].filter(Boolean);
    const medals  = top3[1] ? ['🥈', '🥇', '🥉'] : ['🥇', '🥉'];
    const heights = top3[1] ? ['110px', '155px', '80px'] : ['155px', '80px'];

    podium.innerHTML = displayOrder.map((p, i) => `
        <div class="podium-block" style="height:${heights[i]}">
            <div class="podium-medal">${medals[i]}</div>
            <div class="podium-name">${escapeHtml(p.name)}</div>
            <div class="podium-score">${p.score}/10</div>
        </div>
    `).join('');
}

function renderTable(leaderboard) {
    const tbody = document.getElementById('results-body');
    tbody.innerHTML = leaderboard.map((p, i) => `
        <tr class="${i === 0 ? 'winner-row' : ''}">
            <td class="rank-cell">${p.rank || i + 1}</td>
            <td>${escapeHtml(p.name)}</td>
            <td><strong>${p.score}</strong>/10</td>
            <td>${formatTime(p.total_time_ms)}</td>
        </tr>
    `).join('');
}

async function loadResults() {
    // Prefer cached leaderboard set by game.js before redirect
    const cached = sessionStorage.getItem('gc_leaderboard');
    if (cached) {
        const leaderboard = JSON.parse(cached);
        renderPodium(leaderboard);
        renderTable(leaderboard);
        return;
    }

    // Fallback: fetch from API (e.g. direct page load / share link)
    try {
        const res  = await fetch(`/api/session/${code}/status`);
        const data = await res.json();
        renderPodium(data.players);
        renderTable(data.players);
    } catch (e) {
        document.getElementById('results-body').innerHTML =
            '<tr><td colspan="4" class="loading-row">Failed to load results.</td></tr>';
    }
}

$('#share-btn').on('click', function () {
    navigator.clipboard.writeText(window.location.href).then(() => {
        $('#share-btn').text('Copied!');
        setTimeout(() => $('#share-btn').text('Copy Link'), 2000);
    });
});

loadResults();
