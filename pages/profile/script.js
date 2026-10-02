/*
 * NFC browser support is shared by the profile and school pages.
 * The implementation lives in /script/nfc.js, loaded globally by index.phtml.
 */

(function () {
    const stats = document.querySelector('.stats[data-profile-stats-user="<?=(int)$user->id; ?>"]');
    const form = stats && stats.querySelector('.profile_stats_period_form');
    if (!form)
        return;

    const message = form.querySelector('.profile_stats_period_message');
    const button = form.querySelector('button[type="submit"]');
    let latestRequest = 0;
    const chartNodes = (container) => Array.from(
        container.querySelectorAll('.tabpanel > .tabcontent > div')
    ).slice(0, 4).map((tab) => tab.querySelector('.statsin'));

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const from = form.elements.profile_stats_start.value;
        const to = form.elements.profile_stats_end.value;
        const fromDay = Date.parse(from + 'T00:00:00Z');
        const toDay = Date.parse(to + 'T00:00:00Z');
        if (!Number.isFinite(fromDay) || !Number.isFinite(toDay) ||
            toDay < fromDay || toDay - fromDay >= 730 * 86400000) {
            message.textContent = 'Choisis des dates dans l’ordre, sur deux ans maximum.';
            return;
        }

        const requestId = ++latestRequest;
        button.disabled = true;
        message.textContent = 'Chargement…';
        const url = new URL('/index.php', window.location.origin);
        url.searchParams.set('p', 'ProfileMenu');
        url.searchParams.set('a', form.elements.a.value);
        url.searchParams.set('profile_stats_start', from);
        url.searchParams.set('profile_stats_end', to);
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok)
                throw new Error('HTTP ' + response.status);
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.querySelector('.stats[data-profile-stats-user="<?=(int)$user->id; ?>"]');
            const before = chartNodes(stats);
            const after = updated && chartNodes(updated);
            if (!after || before.length !== 4 || after.length !== 4 ||
                after.some((node) => !node || !node.style.backgroundImage))
                throw new Error('Graphiques indisponibles');
            if (requestId !== latestRequest)
                return;
            before.forEach((node, index) => {
                node.style.backgroundImage = after[index].style.backgroundImage;
            });
            const current = new URL(window.location.href);
            current.searchParams.set('profile_stats_start', from);
            current.searchParams.set('profile_stats_end', to);
            history.replaceState(history.state, '', current);
            message.textContent = 'Période mise à jour.';
        } catch (error) {
            if (requestId === latestRequest)
                message.textContent = 'Impossible de charger les graphiques. Réessaie.';
        } finally {
            if (requestId === latestRequest)
                button.disabled = false;
        }
    });
})();
