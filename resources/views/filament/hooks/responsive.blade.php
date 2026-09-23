<style>
    /*
     * Styles for the panel's hand-written pages.
     *
     * The Docker images build PHP only (no npm step), so Tailwind utility
     * classes are never compiled for the admin panel. Everything below is
     * plain CSS on top of Filament's own design tokens, so the custom pages
     * pick up the panel's colours, radii and dark mode for free.
     */

    /* Admin: prevent horizontal page scroll; keep tables swipeable. */
    .fi-body, .fi-main, .fi-page {
        max-width: 100%;
    }
    .fi-ta-content {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
    }
    /* A table narrower than its columns scrolls. On a mouse, overlay
       scrollbars stay hidden until you scroll, so the cut-off column reads
       as broken rather than swipeable — give it a permanent thin bar. */
    @media (hover: hover) and (pointer: fine) {
        /* Same story for a tab strip too wide for a narrow window. */
        .fi-tabs {
            scrollbar-width: thin;
            scrollbar-color: var(--gray-300) transparent;
        }
        .fi-tabs::-webkit-scrollbar {
            height: 0.375rem;
        }
        .fi-tabs::-webkit-scrollbar-track {
            background: transparent;
        }
        .fi-tabs::-webkit-scrollbar-thumb {
            background-color: var(--gray-300);
            border-radius: 999px;
        }
        .dark .fi-tabs {
            scrollbar-color: var(--gray-600) transparent;
        }
        .dark .fi-tabs::-webkit-scrollbar-thumb {
            background-color: var(--gray-600);
        }
        .fi-ta-content {
            scrollbar-width: thin;
            scrollbar-color: var(--gray-300) transparent;
        }
        .fi-ta-content::-webkit-scrollbar {
            height: 0.5rem;
        }
        .fi-ta-content::-webkit-scrollbar-track {
            background: transparent;
        }
        .fi-ta-content::-webkit-scrollbar-thumb {
            background-color: var(--gray-300);
            border-radius: 999px;
        }
        .dark .fi-ta-content {
            scrollbar-color: var(--gray-600) transparent;
        }
        .dark .fi-ta-content::-webkit-scrollbar-thumb {
            background-color: var(--gray-600);
        }
    }
    .fi-fo-wizard-header,
    .fi-sc-wizard-header {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    /* On a wide screen, share the row between the steps and let their
       descriptions wrap, instead of clipping the last one at the edge. */
    @media (min-width: 1024px) {
        .fi-fo-wizard-header,
        .fi-sc-wizard-header {
            grid-auto-columns: minmax(0, 1fr);
            overflow-x: visible;
        }
        .fi-fo-wizard-header-step,
        .fi-sc-wizard-header-step,
        .fi-fo-wizard-header-step-btn,
        .fi-sc-wizard-header-step-btn,
        .fi-fo-wizard-header-step-text,
        .fi-sc-wizard-header-step-text {
            min-width: 0;
        }
        .fi-fo-wizard-header-step-text,
        .fi-sc-wizard-header-step-text {
            width: auto;
            max-width: none;
        }
    }

    /*
     * Filament 4.12 forces every header cell of a stacked table visible from
     * `sm` up, which outnumbers the body cells that `visibleFrom()` still
     * hides — the header row then runs past the columns under it. These
     * restore the breakpoint the column asked for.
     */
    .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.sm\:fi-visible { display: none; }
    @media (min-width: 40rem) {
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.sm\:fi-visible { display: table-cell; }
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.sm\:fi-hidden { display: none; }
    }
    .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.md\:fi-visible { display: none; }
    @media (min-width: 48rem) {
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.md\:fi-visible { display: table-cell; }
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.md\:fi-hidden { display: none; }
    }
    .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.lg\:fi-visible { display: none; }
    @media (min-width: 64rem) {
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.lg\:fi-visible { display: table-cell; }
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.lg\:fi-hidden { display: none; }
    }
    .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.xl\:fi-visible { display: none; }
    @media (min-width: 80rem) {
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.xl\:fi-visible { display: table-cell; }
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.xl\:fi-hidden { display: none; }
    }
    .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.\32 xl\:fi-visible { display: none; }
    @media (min-width: 96rem) {
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.\32 xl\:fi-visible { display: table-cell; }
        .fi-ta-table.fi-ta-table-stacked-on-mobile > thead > tr > .fi-ta-header-cell.\32 xl\:fi-hidden { display: none; }
    }

    code, .fi-font-mono {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    /* Vertical rhythm inside a page. */
    .omsp-stack {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 6);
    }
    .omsp-stack-sm {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 3);
    }

    /* Body copy: intro paragraphs, help text, list items. */
    .omsp-prose {
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--gray-600);
        margin: 0;
        max-width: 75ch;
    }
    .dark .omsp-prose {
        color: var(--gray-400);
    }
    .omsp-prose strong {
        font-weight: 600;
        color: var(--gray-950);
    }
    .dark .omsp-prose strong {
        color: var(--color-white, #fff);
    }

    /* Numbered how-to steps. Filament's reset strips list markers. */
    .omsp-steps {
        margin: 0;
        padding-inline-start: calc(var(--spacing) * 5);
        list-style: decimal;
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 1.5);
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--gray-600);
        max-width: 75ch;
    }
    .dark .omsp-steps {
        color: var(--gray-400);
    }
    .omsp-steps li {
        padding-inline-start: calc(var(--spacing) * 1);
    }

    /* Label above a value, used for read-only connection details. */
    .omsp-label {
        font-size: var(--text-xs);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--gray-500);
    }
    .dark .omsp-label {
        color: var(--gray-400);
    }

    /* A copyable value: monospace box plus a button that never wraps. */
    .omsp-field {
        display: flex;
        flex-direction: column;
        /* Stacked on a phone, the button sizes to its label instead of
           turning into a full-width bar under every value. */
        align-items: flex-end;
        gap: calc(var(--spacing) * 2);
        margin: calc(var(--spacing) * 1) 0 0;
    }
    .omsp-field > .omsp-code {
        align-self: stretch;
    }
    .omsp-code {
        display: block;
        flex: 1 1 auto;
        min-width: 0;
        border-radius: var(--radius-lg);
        background-color: var(--gray-50);
        border: 1px solid var(--gray-200);
        padding: calc(var(--spacing) * 2) calc(var(--spacing) * 3);
        font-family: var(--font-mono);
        font-size: var(--text-sm);
        line-height: 1.5;
        color: var(--gray-950);
        overflow-wrap: anywhere;
    }
    .dark .omsp-code {
        background-color: var(--gray-900);
        border-color: var(--gray-700);
        color: var(--gray-100);
    }
    .omsp-code-muted {
        color: var(--gray-500);
        font-style: italic;
    }
    @media (min-width: 640px) {
        .omsp-field {
            flex-direction: row;
            align-items: center;
        }
        .omsp-field > .fi-btn,
        .omsp-field > .fi-link {
            flex: 0 0 auto;
        }
    }

    /* Definition list of connection values. */
    .omsp-fields {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 4);
        margin: 0;
    }

    /* Summary figures, e.g. the last Pax8 sync report. */
    .omsp-summary {
        display: grid;
        grid-template-columns: 1fr;
        gap: calc(var(--spacing) * 2) calc(var(--spacing) * 6);
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: var(--text-sm);
    }
    @media (min-width: 640px) {
        .omsp-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    .omsp-summary > div {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: calc(var(--spacing) * 3);
        border-bottom: 1px solid var(--gray-100);
        padding-bottom: calc(var(--spacing) * 2);
    }
    .dark .omsp-summary > div {
        border-color: var(--gray-800);
    }
    .omsp-summary dt {
        color: var(--gray-500);
    }
    .dark .omsp-summary dt {
        color: var(--gray-400);
    }
    .omsp-summary dd {
        margin: 0;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: var(--gray-950);
    }
    .dark .omsp-summary dd {
        color: var(--gray-100);
    }

    /* Cards listing the MCP tools / imported products. */
    .omsp-tiles {
        display: grid;
        grid-template-columns: 1fr;
        gap: calc(var(--spacing) * 2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    @media (min-width: 640px) {
        .omsp-tiles {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    .omsp-tile {
        border-radius: var(--radius-lg);
        background-color: var(--gray-50);
        padding: calc(var(--spacing) * 2) calc(var(--spacing) * 3);
    }
    .dark .omsp-tile {
        background-color: var(--gray-900);
    }
    .omsp-tile-title {
        font-family: var(--font-mono);
        font-size: var(--text-xs);
        font-weight: 500;
        color: var(--gray-950);
        overflow-wrap: anywhere;
    }
    .dark .omsp-tile-title {
        color: var(--gray-100);
    }
    .omsp-tile-text {
        margin: calc(var(--spacing) * 0.5) 0 0;
        font-size: var(--text-xs);
        line-height: 1.5;
        color: var(--gray-500);
    }
    .dark .omsp-tile-text {
        color: var(--gray-400);
    }

    /* Row of buttons that wraps instead of overflowing on a phone. */
    .omsp-actions {
        display: flex;
        flex-wrap: wrap;
        gap: calc(var(--spacing) * 2);
    }
    @media (max-width: 639px) {
        .omsp-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .omsp-actions .fi-btn {
            width: 100%;
        }
    }

    /* Search form above the Pax8 catalog results. */
    .omsp-search {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 2);
    }
    @media (min-width: 640px) {
        .omsp-search {
            flex-direction: row;
            align-items: flex-start;
        }
        .omsp-search-main {
            flex: 1 1 auto;
            min-width: 0;
        }
        .omsp-search-vendor {
            flex: 0 1 12rem;
        }
        .omsp-search .fi-btn {
            flex: 0 0 auto;
        }
    }

    /* Plain data table on a custom page (Filament tables style themselves). */
    .omsp-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .omsp-table {
        width: 100%;
        min-width: 32rem;
        border-collapse: collapse;
        font-size: var(--text-sm);
        text-align: start;
    }
    .omsp-table th {
        font-size: var(--text-xs);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--gray-500);
        padding: calc(var(--spacing) * 2) calc(var(--spacing) * 3) calc(var(--spacing) * 2) 0;
        white-space: nowrap;
    }
    .dark .omsp-table th {
        color: var(--gray-400);
    }
    .omsp-table td {
        padding: calc(var(--spacing) * 3) calc(var(--spacing) * 3) calc(var(--spacing) * 3) 0;
        border-top: 1px solid var(--gray-100);
        vertical-align: middle;
        color: var(--gray-950);
    }
    .dark .omsp-table td {
        border-color: var(--gray-800);
        color: var(--gray-100);
    }
    .omsp-table th:last-child,
    .omsp-table td:last-child {
        padding-inline-end: 0;
        text-align: end;
        white-space: nowrap;
    }
    .omsp-table-mono {
        font-family: var(--font-mono);
        font-size: var(--text-xs);
        overflow-wrap: anywhere;
    }

    /* Bulleted list of imported products. */
    .omsp-list {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 2);
        font-size: var(--text-sm);
    }
    .omsp-list > li {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: calc(var(--spacing) * 1) calc(var(--spacing) * 3);
        border-bottom: 1px solid var(--gray-100);
        padding-bottom: calc(var(--spacing) * 2);
        color: var(--gray-950);
    }
    .dark .omsp-list > li {
        border-color: var(--gray-800);
        color: var(--gray-100);
    }
    .omsp-list > li:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .omsp-list-meta {
        font-size: var(--text-xs);
        color: var(--gray-500);
    }
    .dark .omsp-list-meta {
        color: var(--gray-400);
    }

    .omsp-error {
        margin: calc(var(--spacing) * 3) 0 0;
        font-size: var(--text-sm);
        line-height: 1.5;
        color: var(--danger-600);
    }
    .dark .omsp-error {
        color: var(--danger-400);
    }

    /* Visually hidden, but still read out. */
    .omsp-sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border-width: 0;
    }

    /* Demo notice above the panel. */
    .omsp-banner {
        width: 100%;
        border-bottom: 1px solid var(--warning-300);
        background-color: var(--warning-50);
        padding: calc(var(--spacing) * 2) calc(var(--spacing) * 4);
        text-align: center;
        font-size: var(--text-sm);
        line-height: 1.5;
        color: var(--warning-900);
    }
    .dark .omsp-banner {
        border-color: var(--warning-700);
        background-color: var(--warning-950);
        color: var(--warning-100);
    }

    /*
     * Sign-in screens: the six one-time-code boxes are one object under a
     * centred heading, not a field that starts at the card's left edge.
     * Scoped to the signed-out layout — inside the panel the same input sits
     * in an ordinary labelled form, where left is right.
     */
    .fi-simple-layout .fi-one-time-code-input-ctn {
        /* The row is only as wide as the six boxes, so centring its contents
           does nothing — the row itself has to move. */
        margin-inline: auto;
    }

    /* Language picker in the topbar and on the signed-out screens. */
    .omsp-locale {
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-lg);
        background-color: var(--color-white, #fff);
        color: var(--gray-950);
        font-size: var(--text-sm);
        min-height: 2.25rem;
        padding: 0 calc(var(--spacing) * 2);
    }
    .dark .omsp-locale {
        border-color: var(--gray-600);
        background-color: var(--gray-900);
        color: var(--gray-100);
    }
    .omsp-locale-float {
        position: absolute;
        top: calc(var(--spacing) * 4);
        inset-inline-end: calc(var(--spacing) * 4);
        z-index: 40;
    }
    @media (max-width: 480px) {
        .omsp-locale-float {
            position: relative;
            top: auto;
            inset-inline-end: auto;
            display: flex;
            justify-content: flex-end;
            padding: calc(var(--spacing) * 3) calc(var(--spacing) * 4) 0;
        }
    }

    @media (max-width: 640px) {
        .fi-header-heading {
            font-size: 1.15rem;
            line-height: 1.3;
        }
        .fi-ac, .fi-header-actions {
            width: 100%;
        }
        .fi-btn {
            min-height: 2.5rem;
        }
    }
    @media (max-width: 768px) {
        .fi-wi-stats-overview-stats-ctn {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 480px) {
        .fi-wi-stats-overview-stats-ctn {
            grid-template-columns: 1fr;
        }
    }

    /*
     * Dashboard watchlist deck: a row of tabs over one table at a time.
     * Alpine hides the rest, so the tab strip has to read as a control on
     * the page background rather than as a heading on a card.
     */
    [x-cloak] {
        display: none !important;
    }
    .omsp-board-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: calc(var(--spacing) * 2);
        margin-bottom: calc(var(--spacing) * 4);
    }
    .omsp-board-tab {
        display: inline-flex;
        align-items: center;
        gap: calc(var(--spacing) * 2);
        min-height: 2.25rem;
        padding: 0 calc(var(--spacing) * 3);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        background-color: var(--color-white, #fff);
        color: var(--gray-700);
        font-size: var(--text-sm);
        font-weight: 500;
        line-height: 1.25rem;
        cursor: pointer;
    }
    .omsp-board-tab:hover {
        background-color: var(--gray-50);
    }
    .omsp-board-tab:focus-visible {
        outline: 2px solid var(--primary-600);
        outline-offset: 2px;
    }
    .omsp-board-tab-on,
    .omsp-board-tab-on:hover {
        border-color: var(--primary-600);
        background-color: var(--primary-600);
        color: var(--color-white, #fff);
    }
    .dark .omsp-board-tab {
        border-color: var(--gray-700);
        background-color: var(--gray-900);
        color: var(--gray-300);
    }
    .dark .omsp-board-tab:hover {
        background-color: var(--gray-800);
    }
    .dark .omsp-board-tab-on,
    .dark .omsp-board-tab-on:hover {
        border-color: var(--primary-500);
        background-color: var(--primary-500);
        color: var(--gray-950);
    }
    /* The count sits on the tab, so it needs a surface of its own on both
       the resting and the selected tab. */
    .omsp-board-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.375rem;
        padding: 0 calc(var(--spacing) * 1.5);
        border-radius: 999px;
        font-size: var(--text-xs);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        line-height: 1.25rem;
    }
    .omsp-board-count-danger {
        background-color: var(--danger-100);
        color: var(--danger-700);
    }
    .omsp-board-count-warning {
        background-color: var(--warning-100);
        color: var(--warning-700);
    }
    .omsp-board-count-primary {
        background-color: var(--gray-100);
        color: var(--gray-700);
    }
    .omsp-board-tab-on .omsp-board-count {
        background-color: rgb(255 255 255 / 0.25);
        color: inherit;
    }
    .dark .omsp-board-count-danger {
        background-color: var(--danger-400);
        color: var(--gray-950);
    }
    .dark .omsp-board-count-warning {
        background-color: var(--warning-400);
        color: var(--gray-950);
    }
    .dark .omsp-board-count-primary {
        background-color: var(--gray-700);
        color: var(--gray-200);
    }
    .dark .omsp-board-tab-on .omsp-board-count {
        background-color: rgb(0 0 0 / 0.15);
        color: inherit;
    }
</style>
