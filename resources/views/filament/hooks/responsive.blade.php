<style>
    /* Admin: prevent horizontal page scroll; keep tables swipeable. */
    .fi-body, .fi-main, .fi-page {
        max-width: 100%;
    }
    .fi-ta-content {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
    }
    .fi-fo-wizard-header,
    .fi-sc-wizard-header {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    code, .fi-font-mono {
        overflow-wrap: anywhere;
        word-break: break-word;
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
</style>
