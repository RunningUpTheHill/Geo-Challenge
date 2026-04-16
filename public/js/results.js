const resultsApp = window.GEO_CHALLENGE || {};
const resultsAuth = window.GEO_PLAYER_AUTH.requireSession(resultsApp.sessionCode, resultsApp.urls.home);

if (!resultsAuth) {
    throw new Error('Missing player session.');
}

let totalQuestions = 0;
const resultsApiUrl = window.GEO_PLAYER_AUTH.withPlayerToken(resultsApp.resultsApiUrl, resultsAuth.playerToken);

function resultsEscapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatTime(ms) {
    if (!ms) {
        return '-';
    }

    return `${(ms / 1000).toFixed(1)}s`;
}

function renderPodium(players) {
    const topThree = players.slice(0, 3);
    const $podium = $('#podium');

    if (topThree.length === 0) {
        $podium.html('<p class="text-center subtle-copy mb-0">No players found.</p>');
        return;
    }

    const displayOrder = [topThree[1], topThree[0], topThree[2]].filter(Boolean);
    const medals = topThree[1] ? ['🥈', '🥇', '🥉'] : ['🥇', '🥉'];
    const heights = topThree[1] ? ['150px', '220px', '120px'] : ['220px', '120px'];

    $podium.html(displayOrder.map((player, index) => `
        <div class="podium-block ${index === 1 || displayOrder.length === 1 ? 'featured' : ''}" style="height:${heights[index]}">
            <div class="podium-medal">${medals[index]}</div>
            <div class="podium-name">${resultsEscapeHtml(player.name)}</div>
            <div class="podium-score">${player.correct_answers}/${totalQuestions} correct</div>
            <div class="subtle-copy small">${player.score} pts</div>
        </div>
    `).join(''));
}

function renderTable(players) {
    $('#results-body').html(players.map((player, index) => `
        <tr class="${index === 0 ? 'winner-row' : ''}">
            <td class="rank-cell">${player.rank || index + 1}</td>
            <td>${resultsEscapeHtml(player.name)}</td>
            <td><strong>${player.correct_answers} / ${totalQuestions}</strong></td>
            <td>${player.score} pts</td>
            <td>${formatTime(player.total_time_ms)}</td>
        </tr>
    `).join(''));
}

function loadResults() {
    $.getJSON(resultsApiUrl).done((data) => {
        totalQuestions = data.num_questions;
        renderPodium(data.players);
        renderTable(data.players);
    }).fail((xhr) => {
        if (window.GEO_PLAYER_AUTH.handleAuthFailure(xhr, resultsApp.sessionCode, resultsApp.urls.home)) {
            return;
        }

        $('#results-body').html('<tr><td colspan="5" class="loading-row">Failed to load results.</td></tr>');
    });
}

$('#share-btn').on('click', () => {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const originalText = $('#share-btn').text();
        $('#share-btn').text('Copied!');
        window.setTimeout(() => {
            $('#share-btn').text(originalText);
        }, 1800);
    });
});

loadResults();
