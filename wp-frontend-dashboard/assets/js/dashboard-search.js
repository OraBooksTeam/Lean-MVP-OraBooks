/**
 * Dashboard Search Module
 * Handles search functionality and modal
 */

(function($) {
    'use strict';

    const DashboardSearch = {
        searchTimeout: null,
        selectedResultIndex: -1,

        init: function() {
            const $searchContainer = $('#search-container');
            
            // Click search container to open modal
            $searchContainer.on('click', (e) => {
                e.preventDefault();
                this.showSearchModal();
            });
            
            // Global keyboard shortcut (Ctrl+K or Cmd+K)
            $(document).on('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    this.showSearchModal();
                }
            });
        },

        getItemIcon: function(type, item) {
            const icons = {
                post: '📝',
                page: '📄',
                user: '👤',
                media: '🖼️'
            };
            return icons[type] || '📄';
        },

        getOrCreateSearchModal: function() {
            let $modal = $('#search-modal-container');
            if ($modal.length === 0) {
                $modal = $(`
                    <div id="search-modal-container" class="fixed inset-0 z-50 hidden items-start justify-center pt-20 bg-black/50 backdrop-blur-sm" onclick="DashboardSearch.hideSearchResults()">
                        <div id="search-modal-content" class="bg-white rounded-3xl shadow-2xl w-[90%] max-w-2xl max-h-[70vh] flex flex-col overflow-hidden scale-95 opacity-0 transition-all duration-200" onclick="event.stopPropagation()">
                            <!-- Search Input Header -->
                            <div class="p-4 border-b border-gray-100 flex items-center gap-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" id="search-modal-input" placeholder="Search everything..." 
                                    class="flex-1 text-lg border-none outline-none placeholder-gray-400"
                                    oninput="DashboardSearch.handleModalSearch(this.value)">
                                <button onclick="DashboardSearch.hideSearchResults()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                                <div class="hidden sm:flex items-center gap-1 text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">
                                    <kbd class="font-sans">ESC</kbd>
                                    <span>to close</span>
                                </div>
                            </div>
                            <!-- Results -->
                            <div id="search-modal-results" class="flex-1 overflow-y-auto">
                                <div class="p-8 text-center text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    <p>Type to search posts, pages, media, and users...</p>
                                </div>
                            </div>
                            <!-- Footer -->
                            <div class="p-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between text-xs text-gray-500">
                                <span id="search-modal-count"></span>
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-300 rounded px-1">↑</kbd> <kbd class="bg-white border border-gray-300 rounded px-1">↓</kbd> to navigate</span>
                                    <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-300 rounded px-1">↵</kbd> to select</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append($modal);
                
                // Keyboard navigation for modal
                $modal.on('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.hideSearchResults();
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.navigateSearchResults('down');
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.navigateSearchResults('up');
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        this.selectSearchResult();
                    }
                });
            }
            return $modal;
        },

        handleModalSearch: function(query) {
            clearTimeout(this.searchTimeout);
            if (query.length > 1) {
                this.searchTimeout = setTimeout(() => this.performSearch(query), 300);
            } else {
                $('#search-modal-results').html(`
                    <div class="p-8 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <p>Type at least 2 characters to search...</p>
                    </div>
                `);
                $('#search-modal-count').text('');
            }
        },

        performSearch: async function(query) {
            const $results = $('#search-modal-results');
            $results.html(`
                <div class="p-8 space-y-3">
                    ${[1,2,3].map(() => `
                        <div class="flex items-center gap-4 p-3 rounded-xl bg-gray-50 animate-pulse">
                            <div class="w-10 h-10 bg-gray-200 rounded-lg"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `);

            try {
                const response = await fetch(`${wpfdVars.restUrl}/dashboard/search?q=${encodeURIComponent(query)}&limit=10`, {
                    headers: { 'X-WP-Nonce': wpfdVars.restNonce }
                });
                
                if (!response.ok) throw new Error('Search failed');
                
                const data = await response.json();
                this.displayModalSearchResults(data);
            } catch (error) {
                console.error('Search error:', error);
                $results.html(`
                    <div class="p-8 text-center text-red-500">
                        <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p>Error loading results. Please try again.</p>
                    </div>
                `);
            }
        },

        displayModalSearchResults: function(data) {
            const $results = $('#search-modal-results');
            const $count = $('#search-modal-count');
            let html = '';
            let totalResults = 0;

            const sections = [
                { title: 'Posts', items: data.posts, type: 'post' },
                { title: 'Pages', items: data.pages, type: 'page' },
                { title: 'Media', items: data.media, type: 'media' },
                { title: 'Users', items: data.users, type: 'user' }
            ];

            sections.forEach(section => {
                if (section.items && section.items.length > 0) {
                    html += `<div class="px-4 py-2 bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider">${section.title}</div>`;
                    section.items.forEach(item => {
                        totalResults++;
                        html += this.renderModalSearchResultItem(item, section.type);
                    });
                }
            });

            if (totalResults === 0) {
                html = `
                    <div class="p-8 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <p>No results found for "${this.escapeHtml(data.query)}"</p>
                    </div>
                `;
            }

            $results.html(html || '<div class="p-8 text-center text-gray-400">No results found</div>');
            $count.text(totalResults > 0 ? `${totalResults} result${totalResults > 1 ? 's' : ''}` : '');
            this.selectedResultIndex = -1;
        },

        renderModalSearchResultItem: function(item, type) {
            const icon = this.getItemIcon(type, item);
            const status = item.status ? `<span class="px-2 py-0.5 rounded-full text-xs font-bold ${item.status === 'publish' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}">${item.status}</span>` : '';
            const date = item.date ? this.formatDate(item.date) : '';
            
            return `
                <div class="search-result-item flex items-center gap-4 p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-0" data-url="${item.url || '#'}" onclick="window.location.href='${item.url || '#'}'">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-xl">${icon}</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 truncate">${this.escapeHtml(item.title || item.name)}</div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 mt-0.5">
                            ${status}
                            ${date ? `<span>${date}</span>` : ''}
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
            `;
        },

        hideSearchResults: function() {
            const $modal = $('#search-modal-container');
            const $content = $('#search-modal-content');
            if ($modal.length) {
                $content.addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');
                setTimeout(() => {
                    $modal.addClass('hidden').removeClass('flex');
                }, 200);
            }
            // Also hide old search results if they exist
            $('#search-results').removeClass('show');
        },

        showSearchModal: function() {
            const $modal = this.getOrCreateSearchModal();
            const $content = $('#search-modal-content');
            const $input = $('#search-modal-input');
            
            // Show modal
            $modal.removeClass('hidden').addClass('flex');
            
            // Animate in
            setTimeout(() => {
                $content.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
            }, 10);
            
            // Focus input
            $input.focus();
        },

        navigateSearchResults: function(direction) {
            const $items = $('#search-modal-results .search-result-item');
            if ($items.length === 0) return;

            $items.removeClass('selected bg-primary-50');
            
            if (direction === 'down') {
                this.selectedResultIndex = (this.selectedResultIndex + 1) % $items.length;
            } else {
                this.selectedResultIndex = this.selectedResultIndex <= 0 ? $items.length - 1 : this.selectedResultIndex - 1;
            }

            const $selected = $items.eq(this.selectedResultIndex);
            $selected.addClass('selected bg-primary-50');
            $selected[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        },

        selectSearchResult: function() {
            const $selected = $('#search-modal-results .search-result-item.selected');
            if ($selected.length > 0) {
                window.location.href = $selected.data('url');
            }
        },

        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        DashboardSearch.init();
    });

    // Make available globally for onclick handlers
    window.DashboardSearch = DashboardSearch;

})(jQuery);
