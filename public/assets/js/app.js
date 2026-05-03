/**
 * Wizdam Sicola – JavaScript Utama
 * Vanilla JS, tanpa framework/bundler.
 */

'use strict';

// ── Aktifkan nav link sesuai path aktif ───────────────────────────────────────
(function highlightActiveNav() {
    const path  = window.location.pathname;
    const links = document.querySelectorAll('header nav a');
    links.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href !== '/' && path.startsWith(href)) {
            link.classList.add('text-indigo-700', 'font-semibold');
        }
    });
})();

// ── Auto-dismiss flash messages ───────────────────────────────────────────────
(function autoDismissFlash() {
    const flashes = document.querySelectorAll('[data-flash]');
    flashes.forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .5s';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });
})();

// ── Konfirmasi sebelum aksi destruktif ────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
        const msg = this.dataset.confirm || 'Apakah Anda yakin?';
        if (!confirm(msg)) e.preventDefault();
    });
});

// ── Tooltips sederhana via data-tooltip ──────────────────────────────────────
(function setupTooltips() {
    let tip = null;

    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', function (e) {
            tip = document.createElement('div');
            tip.textContent = this.dataset.tooltip;
            tip.className   = 'tooltip-bubble';
            Object.assign(tip.style, {
                position:  'fixed',
                top:       (e.clientY - 32) + 'px',
                left:      (e.clientX + 8)  + 'px',
                padding:   '4px 8px',
                fontSize:  '11px',
                background:'#1e293b',
                color:     '#fff',
                borderRadius: '6px',
                zIndex:    '9999',
                pointerEvents: 'none',
                whiteSpace: 'nowrap',
            });
            document.body.appendChild(tip);
        });

        el.addEventListener('mousemove', function (e) {
            if (tip) {
                tip.style.top  = (e.clientY - 32) + 'px';
                tip.style.left = (e.clientX + 8)  + 'px';
            }
        });

        el.addEventListener('mouseleave', function () {
            if (tip) { tip.remove(); tip = null; }
        });
    });
})();

// ── Copy to clipboard ─────────────────────────────────────────────────────────
document.querySelectorAll('[data-copy]').forEach(el => {
    el.addEventListener('click', async function () {
        const text = this.dataset.copy;
        try {
            await navigator.clipboard.writeText(text);
            const orig = this.textContent;
            this.textContent = '✓ Disalin!';
            setTimeout(() => { this.textContent = orig; }, 2000);
        } catch {
            // fallback
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity  = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
    });
});
