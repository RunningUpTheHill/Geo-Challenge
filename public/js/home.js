const homeApp = window.GEO_CHALLENGE || {};

function homeRoute(path) {
    return `${homeApp.basePath || ''}/${String(path).replace(/^\/+/, '')}`;
}

function persistPlayerAuth(data) {
    return window.GEO_PLAYER_AUTH.saveSession({
        code: data.code,
        playerId: data.player_id,
        playerName: data.player_name,
        playerToken: data.player_token,
    });
}

function toggleInlineAlert($element, message) {
    $element.text(message || '');
    $element.toggleClass('d-none', !message);
}

function setButtonState($button, text, disabled) {
    $button.text(text);
    $button.prop('disabled', disabled);
}

function submitCreateGame() {
    const $error = $('#create-error');
    const $button = $('#create-btn');
    const name = $('#create-name').val().trim();
    const numQuestions = parseInt($('#create-questions').val(), 10);

    toggleInlineAlert($error, '');
    if (name.length < 2) {
        toggleInlineAlert($error, 'Name must be at least 2 characters.');
        return;
    }

    setButtonState($button, 'Creating...', true);

    $.ajax({
        url: homeApp.urls.sessionCreate,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            player_name: name,
            num_questions: numQuestions,
        }),
    }).done((data) => {
        const playerSession = persistPlayerAuth(data);
        if (!playerSession) {
            toggleInlineAlert($error, 'Could not save your player session. Please try again.');
            setButtonState($button, 'Create Game', false);
            return;
        }
        window.location.href = homeRoute(`lobby.php?code=${encodeURIComponent(data.code)}`);
    }).fail((xhr) => {
        const message = xhr.responseJSON && xhr.responseJSON.error
            ? xhr.responseJSON.error
            : 'Network error - please try again.';
        toggleInlineAlert($error, message);
        setButtonState($button, 'Create Game', false);
    });
}

function submitJoinGame() {
    const $error = $('#join-error');
    const $button = $('#join-btn');
    const name = $('#join-name').val().trim();
    const code = $('#join-code').val().trim().toUpperCase();

    toggleInlineAlert($error, '');
    if (name.length < 2) {
        toggleInlineAlert($error, 'Name must be at least 2 characters.');
        return;
    }
    if (code.length !== 6) {
        toggleInlineAlert($error, 'Code must be exactly 6 characters.');
        return;
    }

    setButtonState($button, 'Joining...', true);

    $.ajax({
        url: homeApp.urls.sessionJoin,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            player_name: name,
            code,
        }),
    }).done((data) => {
        const playerSession = persistPlayerAuth(data);
        if (!playerSession) {
            toggleInlineAlert($error, 'Could not save your player session. Please try again.');
            setButtonState($button, 'Join Game', false);
            return;
        }
        window.location.href = homeRoute(`lobby.php?code=${encodeURIComponent(data.code)}`);
    }).fail((xhr) => {
        const message = xhr.responseJSON && xhr.responseJSON.error
            ? xhr.responseJSON.error
            : 'Network error - please try again.';
        toggleInlineAlert($error, message);
        setButtonState($button, 'Join Game', false);
    });
}

$('#create-form').on('submit', (event) => {
    event.preventDefault();
    submitCreateGame();
});

$('#join-form').on('submit', (event) => {
    event.preventDefault();
    submitJoinGame();
});

$('#join-code').on('input', function onCodeInput() {
    $(this).val($(this).val().toUpperCase());
});

/* ── Floating particle canvas ────────────────────────────────────────────── */
(function initParticles() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;';
    document.body.insertBefore(canvas, document.body.firstChild);

    const ctx = canvas.getContext('2d');
    const COLORS = [
        [30, 167, 161],
        [240, 180, 75],
        [60, 207, 145],
    ];
    let W, H, particles, raf;

    function resize() {
        W = canvas.width = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    function makeParticle() {
        const color = COLORS[Math.floor(Math.random() * COLORS.length)];
        return {
            x: Math.random() * W,
            y: Math.random() * H,
            r: Math.random() * 1.3 + 0.4,
            vx: (Math.random() - 0.5) * 0.26,
            vy: (Math.random() - 0.5) * 0.26,
            alpha: Math.random() * 0.3 + 0.07,
            color,
        };
    }

    function init() {
        particles = Array.from({ length: 48 }, makeParticle);
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);
        for (const p of particles) {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(${p.color[0]},${p.color[1]},${p.color[2]},${p.alpha})`;
            ctx.fill();
            p.x += p.vx;
            p.y += p.vy;
            if (p.x < -4) p.x = W + 4;
            else if (p.x > W + 4) p.x = -4;
            if (p.y < -4) p.y = H + 4;
            else if (p.y > H + 4) p.y = -4;
        }
        raf = requestAnimationFrame(draw);
    }

    resize();
    init();
    draw();

    window.addEventListener('resize', () => {
        cancelAnimationFrame(raf);
        resize();
        init();
        draw();
    });
}());

/* ── Button click ripple ─────────────────────────────────────────────────── */
(function initRipple() {
    function attachRipple($btn) {
        $btn.on('click.ripple', function handleRipple(e) {
            const off = $(this).offset();
            const $r = $('<span class="btn-ripple"></span>').css({
                left: e.pageX - off.left,
                top: e.pageY - off.top,
            });
            $(this).append($r);
            setTimeout(() => $r.remove(), 700);
        });
    }

    attachRipple($('#create-btn'));
    attachRipple($('#join-btn'));
}());
