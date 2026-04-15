const GEO_PLAYER_AUTH = (() => {
    const STORAGE_PREFIX = 'geoChallenge.player.';

    function normalizeCode(code) {
        return String(code || '').trim().toUpperCase();
    }

    function storageKey(code) {
        return `${STORAGE_PREFIX}${normalizeCode(code)}`;
    }

    function parseStoredSession(rawValue) {
        if (!rawValue) {
            return null;
        }

        try {
            const data = JSON.parse(rawValue);
            const code = normalizeCode(data.code);
            const playerId = Number(data.playerId || data.player_id || 0);
            const playerName = String(data.playerName || data.player_name || '');
            const playerToken = String(data.playerToken || data.player_token || '').trim();

            if (!code || !playerId || !playerToken) {
                return null;
            }

            return {
                code,
                playerId,
                playerName,
                playerToken,
            };
        } catch (error) {
            return null;
        }
    }

    function saveSession(payload) {
        const session = parseStoredSession(JSON.stringify(payload));
        if (!session) {
            return null;
        }

        try {
            window.sessionStorage.setItem(storageKey(session.code), JSON.stringify(session));
            return session;
        } catch (error) {
            return null;
        }
    }

    function loadSession(code) {
        return parseStoredSession(window.sessionStorage.getItem(storageKey(code)));
    }

    function clearSession(code) {
        const normalizedCode = normalizeCode(code);
        if (!normalizedCode) {
            return;
        }

        window.sessionStorage.removeItem(storageKey(normalizedCode));
    }

    function redirectHome(homeUrl) {
        window.location.replace(homeUrl);
    }

    function requireSession(code, homeUrl) {
        const session = loadSession(code);
        if (!session) {
            redirectHome(homeUrl);
            return null;
        }

        return session;
    }

    function withPlayerToken(url, playerToken) {
        const nextUrl = new URL(url, window.location.origin);
        nextUrl.searchParams.set('player_token', playerToken);
        return `${nextUrl.pathname}${nextUrl.search}${nextUrl.hash}`;
    }

    function isAuthFailure(xhr) {
        return Boolean(xhr && xhr.status === 403);
    }

    function handleAuthFailure(xhr, code, homeUrl) {
        if (!isAuthFailure(xhr)) {
            return false;
        }

        clearSession(code);
        redirectHome(homeUrl);
        return true;
    }

    return {
        saveSession,
        loadSession,
        clearSession,
        requireSession,
        withPlayerToken,
        handleAuthFailure,
        normalizeCode,
    };
})();

window.GEO_PLAYER_AUTH = GEO_PLAYER_AUTH;
