{{--
    The application as HRM-07 prints it, names and all (HR, 2026-11-23). A srcdoc frame, so the
    form's own page styles stay inside it and the app's headers that refuse framing need no
    exception; sandboxed, so no script runs in it. $html is the rendered leave.print view,
    escaped here once more for the attribute, as srcdoc expects.
--}}
<iframe
    class="dtr-leave-form"
    title="Leave application form"
    sandbox
    srcdoc="{{ $html }}"
></iframe>

<style>
    .dtr-leave-form {
        display: block;
        width: 100%;
        /* One A4 sheet and the room around it; on a narrow screen the sheet is smaller and the rest stays grey. */
        height: calc(297mm + 2rem);
        border: 0;
        border-radius: var(--radius-lg);
        background: #e5e7eb;
    }
</style>
