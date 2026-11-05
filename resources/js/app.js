import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Keyboard shortcuts.
 *
 * A shortcut is declared in the markup with `data-nav-shortcut="<key>"` on
 * the element that should be activated. Keeping the mapping in the markup
 * means the badge beside a shortcut and the shortcut itself can never
 * disagree — there is one source of truth.
 *
 * Shortcuts stay out of the way while the user is typing. Anything inside
 * a text field, a select, or a contenteditable region is left alone, and
 * a modifier key means the keystroke belongs to the browser or the OS.
 */
document.addEventListener('keydown', (event) => {
    // A modified keystroke (Cmd/Ctrl/Alt) is never ours to claim.
    if (event.metaKey || event.ctrlKey || event.altKey) return;

    const target = event.target;

    // While the user is typing, every keystroke belongs to the field.
    if (target instanceof HTMLElement) {
        const tag = target.tagName;
        const isEditable =
            tag === 'INPUT' ||
            tag === 'TEXTAREA' ||
            tag === 'SELECT' ||
            target.isContentEditable;

        if (isEditable) return;
    }

    const key = event.key.toLowerCase();

    const shortcut = document.querySelector(`[data-nav-shortcut="${CSS.escape(key)}"]`);

    if (!shortcut) return;

    event.preventDefault();
    shortcut.click();
});

/**
 * Alpine is started last, so every scope a page can ask for — the
 * password policy above, and the toast and confirmation helpers in
 * layouts/app.blade.php — is already defined by the time the markup is
 * walked.
 */
Alpine.start();
