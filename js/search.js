// js/search.js — Production Real Database Search Module

window.performRealBackendSearch = function(params = {}) {
    const query = params.query || (document.getElementById('search-input-field')?.value || '').trim();
    const category = params.category || (document.getElementById('filter-category-select')?.value || '');
    const location = params.location || (document.getElementById('filter-location-select')?.value || '');
    const sort = params.sort || (document.getElementById('filter-sort-select')?.value || 'recommended');
    const verifiedOnly = params.verified || (document.getElementById('filter-verified-checkbox')?.checked ? 1 : 0);
    const premiumOnly = params.premium || (document.getElementById('filter-premium-checkbox')?.checked ? 1 : 0);

    const queryParams = new URLSearchParams();
    if (query) queryParams.append('q', query);
    if (category && category !== 'All') queryParams.append('category', category);
    if (location && location !== 'All') queryParams.append('location', location);
    if (sort) queryParams.append('sort', sort);
    if (verifiedOnly) queryParams.append('verified', 1);
    if (premiumOnly) queryParams.append('premium', 1);

    const resultsContainer = document.getElementById('search-results-container') || document.getElementById('vendors-grid-container');
    if (resultsContainer) {
        resultsContainer.innerHTML = `<div style="grid-column: 1/-1; padding:40px; text-align:center; color:var(--gray-500);"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem; color:var(--primary);"></i><div style="margin-top:8px; font-weight:600;">Searching vendors database...</div></div>`;
    }

    return API.get('search?' + queryParams.toString())
        .then(vendors => {
            state.searchResults = vendors;
            if (typeof renderSearchResultsScreen === 'function') {
                renderSearchResultsScreen(vendors);
            }
            return vendors;
        })
        .catch(err => {
            console.error("Backend search error:", err);
            if (resultsContainer) {
                resultsContainer.innerHTML = `<div style="grid-column: 1/-1; padding:30px; text-align:center; color:var(--error);"><i class="fa-solid fa-triangle-exclamation"></i> Could not fetch search results. Please try again.</div>`;
            }
        });
};

window.initSearchScreenModule = function() {
    const searchField = document.getElementById('search-input-field');
    if (searchField) {
        searchField.addEventListener('keyup', debounce(() => {
            window.performRealBackendSearch();
        }, 400));
    }
};

if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', () => {
        window.initSearchScreenModule();
    });
}
