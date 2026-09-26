<script>
    (() => {
        const selector = '.fi-ta-content-ctn';

        const enhance = (root = document) => {
            const regions = [];

            if (root instanceof Element && root.matches(selector)) {
                regions.push(root);
            }

            if (typeof root.querySelectorAll === 'function') {
                regions.push(...root.querySelectorAll(selector));
            }

            regions.forEach((region) => {
                if (region.tabIndex < 0) {
                    region.tabIndex = 0;
                }

                if (! region.hasAttribute('role')) {
                    region.setAttribute('role', 'region');
                }

                if (! region.hasAttribute('aria-label')) {
                    const tableLabel = region.querySelector('.fi-ta-table')?.getAttribute('aria-label')?.trim();
                    region.setAttribute('aria-label', tableLabel ? `${tableLabel} scroll region` : 'Data table scroll region');
                }
            });
        };

        const start = () => {
            enhance();

            if (window.hoaAccessibleTableRegionsObserver) {
                return;
            }

            window.hoaAccessibleTableRegionsObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node instanceof Element) {
                            enhance(node);
                        }
                    });
                });
            });

            window.hoaAccessibleTableRegionsObserver.observe(document.body, {
                childList: true,
                subtree: true,
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start, { once: true });
        } else {
            start();
        }
    })();
</script>
