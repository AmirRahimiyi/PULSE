
document.addEventListener('DOMContentLoaded', () => {

    /* =====================================
       CATEGORY FILTER
    ===================================== */

    const categoryButtons = document.querySelectorAll('.category-chip');
    const posts = document.querySelectorAll('.post-card[data-category]');
    const searchInput = document.getElementById('feedSearch');

    let selectedCategory = 'all';

    function applyCategoryFilter() {
        const query = searchInput
            ? searchInput.value.trim().toLowerCase()
            : '';

        posts.forEach(post => {
            const category = post.dataset.category || 'uncategorized';

            const categoryMatch =
                selectedCategory === 'all' ||
                category === selectedCategory;

            const textMatch =
                !query || post.textContent.toLowerCase().includes(query);

            post.style.display =
                categoryMatch && textMatch ? '' : 'none';
        });
    }

    categoryButtons.forEach(button => {
        button.addEventListener('click', () => {
            selectedCategory = button.dataset.category || 'all';

            categoryButtons.forEach(item => {
                item.classList.toggle('active', item === button);
            });

            applyCategoryFilter();
        });
    });


    /* =====================================
       DATABASE SEARCH
    ===================================== */

    const searchButton = document.getElementById('topbarSearchButton');
    const searchResults = document.getElementById('databaseSearchResults');
    const searchResultsCount = document.getElementById('searchResultsCount');
    const searchResultsList = document.getElementById('searchResultsList');

    let searchTimeout = null;
    let activeSearchController = null;
    let latestSearchId = 0;

    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }

    function showSearchResults() {
        if (searchResults) {
            searchResults.style.display = '';
        }
    }

    function hideSearchResults() {
        if (searchResults) {
            searchResults.style.display = 'none';
        }

        if (searchResultsList) {
            searchResultsList.innerHTML = '';
        }

        if (searchResultsCount) {
            searchResultsCount.textContent = '';
        }
    }

    function renderSearchResults(data) {
        if (!searchResults || !searchResultsList || !searchResultsCount) {
            return;
        }

        const results = Array.isArray(data.posts)
            ? data.posts
            : Array.isArray(data.results)
                ? data.results
                : [];

        showSearchResults();

        searchResultsCount.textContent =
            `${results.length} result${results.length === 1 ? '' : 's'}`;

        if (!results.length) {
            searchResultsList.innerHTML = `
                <div class="search-empty">
                    No posts found. Try another keyword.
                </div>
            `;
            return;
        }

        searchResultsList.innerHTML = results.map(post => {
            const username = post.username || 'User';
            const title = post.title || 'Untitled post';
            const content = post.content || '';
            const category = post.category_name || 'General';
            const postId = Number(post.id || post.post_id || 0);

            return `
                <article class="search-result-card">

                    <div class="search-result-meta">
                        <a
                            class="profile-link"
                            href="profile.php?u=${encodeURIComponent(username)}"
                        >
                            ${escapeHTML(username)}
                        </a>

                        <span>${escapeHTML(category)}</span>
                    </div>

                    <h3>${escapeHTML(title)}</h3>

                    <p>${escapeHTML(content)}</p>

                    ${postId ? `
                        <a
                            href="index.php#post-${postId}"
                            class="search-result-link"
                        >
                            View discussion →
                        </a>
                    ` : ''}

                </article>
            `;
        }).join('');
    }

    async function searchDatabase(query) {
        query = query.trim();

        if (query.length < 2) {
            hideSearchResults();

            if (activeSearchController) {
                activeSearchController.abort();
                activeSearchController = null;
            }

            return;
        }

        if (activeSearchController) {
            activeSearchController.abort();
        }

        const searchId = ++latestSearchId;
        const controller = new AbortController();

        activeSearchController = controller;

        showSearchResults();

        if (searchResultsCount) {
            searchResultsCount.textContent = 'Searching...';
        }

        if (searchResultsList) {
            searchResultsList.innerHTML = '';
        }

        try {
            const response = await fetch(
                'search-posts.php?q=' + encodeURIComponent(query),
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal
                }
            );

            let data;

            try {
                data = await response.json();
            } catch {
                throw new Error('Invalid server response.');
            }

            if (!response.ok || data.success === false) {
                throw new Error(
                    data.message || 'Search could not be completed.'
                );
            }

            // Prevent outdated requests from overwriting newer results.
            if (searchId !== latestSearchId) {
                return;
            }

            renderSearchResults(data);

        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            if (searchId !== latestSearchId) {
                return;
            }

            console.error('PULSE Search Error:', error);

            showSearchResults();

            if (searchResultsCount) {
                searchResultsCount.textContent = 'Search error';
            }

            if (searchResultsList) {
                searchResultsList.innerHTML = `
                    <div class="search-empty">
                        Search is temporarily unavailable.
                    </div>
                `;
            }
        } finally {
            if (searchId === latestSearchId) {
                activeSearchController = null;
            }
        }
    }


    /* =====================================
       LIVE SEARCH INPUT
    ===================================== */

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            applyCategoryFilter();

            const query = searchInput.value.trim();

            clearTimeout(searchTimeout);

            if (query.length < 2) {
                latestSearchId++;

                if (activeSearchController) {
                    activeSearchController.abort();
                    activeSearchController = null;
                }

                hideSearchResults();
                return;
            }

            searchTimeout = setTimeout(() => {
                searchDatabase(query);
            }, 350);
        });

        searchInput.addEventListener('keydown', event => {

            if (event.key === 'Escape') {
                searchInput.value = '';

                clearTimeout(searchTimeout);

                latestSearchId++;

                if (activeSearchController) {
                    activeSearchController.abort();
                    activeSearchController = null;
                }

                hideSearchResults();
                applyCategoryFilter();

                searchInput.blur();
            }

            if (event.key === 'Enter') {
                event.preventDefault();

                clearTimeout(searchTimeout);

                const query = searchInput.value.trim();

                if (query.length >= 2) {
                    searchDatabase(query);
                }
            }
        });
    }


    /* =====================================
       TOPBAR SEARCH BUTTON
    ===================================== */

    if (searchButton && searchInput) {
        searchButton.addEventListener('click', () => {
            searchInput.focus();

            const query = searchInput.value.trim();

            if (query.length >= 2) {
                clearTimeout(searchTimeout);
                searchDatabase(query);
            }
        });
    }


    /* =====================================
       SEARCH RESULT STYLES
    ===================================== */

    if (!document.getElementById('pulseSearchStyles')) {
        const style = document.createElement('style');

        style.id = 'pulseSearchStyles';

        style.textContent = `
            .database-search-results {
                margin: 14px 0;
                padding: 16px;
                background: #11141b;
                border: 1px solid #292d38;
                border-radius: 12px;
            }

            #searchResultsCount {
                color: #a78bfa;
                font-size: 12px;
                font-weight: 700;
                margin-bottom: 12px;
            }

            #searchResultsList {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .search-result-card {
                padding: 13px;
                background: #171a22;
                border: 1px solid #292d38;
                border-radius: 9px;
                overflow-wrap: anywhere;
            }

            .search-result-card h3 {
                color: #f5f5f7;
                font-size: 14px;
                margin: 8px 0;
            }

            .search-result-card p {
                color: #a1a1aa;
                font-size: 12px;
                line-height: 1.7;
                margin: 0;
                white-space: pre-wrap;
            }

            .search-result-meta {
                display: flex;
                align-items: center;
                gap: 9px;
                flex-wrap: wrap;
                color: #9297a5;
                font-size: 11px;
            }

            .search-result-link {
                display: inline-block;
                margin-top: 10px;
                color: #a78bfa;
                font-size: 12px;
                text-decoration: none;
            }

            .search-result-link:hover {
                color: #c4b5fd;
            }

            .search-empty {
                padding: 12px 0;
                color: #9297a5;
                font-size: 13px;
            }
        `;

        document.head.appendChild(style);
    }


    /* =====================================
       INITIALIZATION
    ===================================== */

    applyCategoryFilter();

    console.log('PULSE main.js initialized.');

});
