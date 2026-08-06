$(function() {
    'use strict';

    // Search Configuration for POS
    const POSSearchConfig = {
        container: '#pos-autocomplete',
        placeholder: 'Search Menu [CTRL + K]',
        classNames: {
            detachedContainer: 'd-flex flex-column',
            detachedFormContainer: 'd-flex align-items-center justify-content-between border-bottom',
            form: 'd-flex align-items-center',
            input: 'search-control border-none',
            detachedCancelButton: 'btn-search-close',
            panel: 'flex-grow content-wrapper overflow-hidden position-relative',
            panelLayout: 'h-100',
            clearButton: 'd-none',
            item: 'd-block'
        }
    };

    // Search state and data
    let posSearchData = {};
    let posSearchInitialized = false;
    let autocompleteRetryCount = 0;
    const MAX_AUTOCOMPLETE_RETRIES = 50; // 5 seconds max wait time

    // Load POS search data
    function loadPOSSearchData() {
        const baseUrl = document.getElementById('base_url')?.value || window.location.origin;
        const searchUrl = baseUrl + '/search-menu-data?type=vertical&context=pos';

        fetch(searchUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch data: ' + response.status);
                }
                return response.json();
            })
            .then(json => {
                if (json && (json.navigation || json.suggestions)) {
                    posSearchData = json;
                    initializePOSAutocomplete();
                } else {
                    throw new Error('Invalid response format');
                }
            })
            .catch(error => {
                console.error('Error loading POS search data:', error);
                // Initialize with empty data
                posSearchData = { navigation: {}, suggestions: {} };
                initializePOSAutocomplete();
            });
    }

    // Initialize POS autocomplete
    function initializePOSAutocomplete() {
        const searchElement = document.getElementById('pos-autocomplete');
        if (!searchElement) {
            console.warn('POS autocomplete element not found');
            return;
        }

        // Wait for autocomplete library to be available
        if (typeof autocomplete === 'undefined') {
            autocompleteRetryCount++;
            if (autocompleteRetryCount < MAX_AUTOCOMPLETE_RETRIES) {
                console.warn('Autocomplete library not loaded yet, retrying... (' + autocompleteRetryCount + '/' + MAX_AUTOCOMPLETE_RETRIES + ')');
                setTimeout(() => initializePOSAutocomplete(), 100);
            } else {
                console.error('Autocomplete library failed to load after ' + MAX_AUTOCOMPLETE_RETRIES + ' retries');
            }
            return;
        }
        
        // Reset retry count on success
        autocompleteRetryCount = 0;

        if (posSearchInitialized) return;
        posSearchInitialized = true;

        const assetsPath = document.documentElement.getAttribute('data-assets-path') || '/../public/backend_assets/';

        const autocompleteInstance = autocomplete({
            ...POSSearchConfig,
            openOnFocus: true,
            onStateChange({ state, setQuery }) {
                if (state.isOpen) {
                    document.body.style.overflow = 'hidden';
                    document.body.style.paddingRight = 'var(--bs-scrollbar-width)';
                    
                    const cancelIcon = document.querySelector('#pos-autocomplete .aa-DetachedCancelButton');
                    if (cancelIcon) {
                        cancelIcon.innerHTML = '<span class="text-body-secondary">[esc]</span> <span class="icon-base icon-md ti tabler-x text-heading"></span>';
                    }

                    if (!window.posAutoCompletePS) {
                        const panel = document.querySelector('#pos-autocomplete .aa-Panel');
                        if (panel && typeof PerfectScrollbar !== 'undefined') {
                            window.posAutoCompletePS = new PerfectScrollbar(panel);
                        }
                    }
                } else {
                    if (state.status === 'idle' && state.query) {
                        setQuery('');
                    }
                    document.body.style.overflow = 'auto';
                    document.body.style.paddingRight = '';
                }
            },
            render(args, root) {
                const { render, html, children, state } = args;

                if (!state.query) {
                    const initialSuggestions = html`
                        <div class="p-5 p-lg-12">
                            <div class="row g-4">
                                ${Object.entries(posSearchData.suggestions || {}).map(
                                    ([section, items]) => html`
                                        <div class="col-md-6 suggestion-section">
                                            <p class="search-headings mb-2">${section}</p>
                                            <div class="suggestion-items">
                                                ${items.map(
                                                    item => html`
                                                        <a href="${item.url}" class="suggestion-item d-flex align-items-center">
                                                            <i class="icon-base ti ${item.icon}"></i>
                                                            <span>${item.name}</span>
                                                        </a>
                                                    `
                                                )}
                                            </div>
                                        </div>
                                    `
                                )}
                            </div>
                        </div>
                    `;
                    render(initialSuggestions, root);
                    return;
                }

                if (!args.sections.length) {
                    render(
                        html`
                            <div class="search-no-results-wrapper">
                                <div class="d-flex justify-content-center align-items-center h-100">
                                    <div class="text-center text-heading">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24">
                                            <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="0.6">
                                                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                                <path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2m-5-4h.01M12 11v3" />
                                            </g>
                                        </svg>
                                        <h5 class="mt-2">No results found</h5>
                                    </div>
                                </div>
                            </div>
                        `,
                        root
                    );
                    return;
                }

                render(children, root);
                if (window.posAutoCompletePS) {
                    window.posAutoCompletePS.update();
                }
            },
            getSources() {
                const sources = [];

                if (posSearchData.navigation) {
                    const navigationSources = Object.keys(posSearchData.navigation)
                        .filter(section => section !== 'files' && section !== 'members')
                        .map(section => ({
                            sourceId: `nav-${section}`,
                            getItems({ query }) {
                                const items = posSearchData.navigation[section];
                                if (!query) return items;
                                return items.filter(item => item.name.toLowerCase().includes(query.toLowerCase()));
                            },
                            getItemUrl({ item }) {
                                return item.url;
                            },
                            templates: {
                                header({ items, html }) {
                                    if (items.length === 0) return null;
                                    return html`<span class="search-headings">${section}</span>`;
                                },
                                item({ item, html }) {
                                    return html`
                                        <a href="${item.url}" class="d-flex justify-content-between align-items-center">
                                            <span class="item-wrapper"><i class="icon-base ti ${item.icon}"></i>${item.name}</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 24 24">
                                                <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" color="currentColor">
                                                    <path d="M11 6h4.5a4.5 4.5 0 1 1 0 9H4" />
                                                    <path d="M7 12s-3 2.21-3 3s3 3 3 3" />
                                                </g>
                                            </svg>
                                        </a>
                                    `;
                                }
                            }
                        }));
                    sources.push(...navigationSources);
                }

                return sources;
            }
        });

        // Store instance for manual triggering
        window.posAutocompleteInstance = autocompleteInstance;
        return autocompleteInstance;
    }

    // Function to trigger POS search
    function triggerPOSSearch() {
        // Ensure autocomplete is initialized
        if (!posSearchInitialized) {
            if (Object.keys(posSearchData).length > 0) {
                initializePOSAutocomplete();
                setTimeout(() => triggerPOSSearch(), 200);
            } else {
                loadPOSSearchData();
                setTimeout(() => triggerPOSSearch(), 300);
            }
            return;
        }

        // Try to find and click the autocomplete detached search button
        setTimeout(() => {
            const autocompleteButton = document.querySelector('#pos-autocomplete .aa-DetachedSearchButton');
            if (autocompleteButton) {
                // Temporarily make it clickable
                autocompleteButton.style.pointerEvents = 'auto';
                autocompleteButton.click();
                // Reset after click
                setTimeout(() => {
                    autocompleteButton.style.pointerEvents = 'none';
                }, 100);
            } else {
                // Fallback: manually show the detached container
                const detachedContainer = document.querySelector('#pos-autocomplete .aa-DetachedContainer');
                if (detachedContainer) {
                    detachedContainer.style.display = 'flex';
                    const searchInput = document.querySelector('#pos-autocomplete .aa-Input');
                    if (searchInput) {
                        setTimeout(() => searchInput.focus(), 50);
                    }
                }
            }
        }, 150);
    }

    // Initialize search shortcut (Ctrl+K or Cmd+K)
    $(document).on('keydown', function(event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            const searchToggler = document.getElementById('pos-menu-search-toggler');
            if (searchToggler) {
                searchToggler.click();
            }
        }
    });

    // Setup search toggler click handler
    // Wait a bit to ensure all scripts are loaded
    setTimeout(function() {
        // Load search data on page load if element exists
        if (document.getElementById('pos-autocomplete')) {
            loadPOSSearchData();
        }

        const searchToggler = document.getElementById('pos-menu-search-toggler');
        if (searchToggler) {
            $(searchToggler).off('click').on('click', function(e) {
                e.preventDefault();
                
                // Ensure search data is loaded
                if (!posSearchInitialized && Object.keys(posSearchData).length === 0) {
                    loadPOSSearchData();
                    // Wait a bit for initialization
                    setTimeout(() => {
                        triggerPOSSearch();
                    }, 300);
                } else {
                    triggerPOSSearch();
                }
            });
        }
    }, 100);
});
