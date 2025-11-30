/**
 * Winning DNA - Product Analysis JavaScript
 * 
 * Handles advanced analysis and visualization for the Winning DNA page
 */

// Immediately attach event listener to the page regardless of load state
(function() {
    // Set up a MutationObserver to watch for DOM changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                // Check if any of the added nodes contain View DNA buttons
                for (let i = 0; i < mutation.addedNodes.length; i++) {
                    const node = mutation.addedNodes[i];
                    if (node.nodeType === 1 && node.classList && 
                        (node.classList.contains('view-dna-btn') || 
                         node.querySelectorAll('.view-dna-btn').length > 0)) {
                        initViewDnaButtons();
                        break;
                    }
                }
            }
        });
    });

    // Start observing the document with the configured parameters
    observer.observe(document.body, { childList: true, subtree: true });
    
    // Also attach directly when script loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAll);
    } else {
        initializeAll();
    }
    
    // Also attach on window load for safety
    window.addEventListener('load', initializeAll);
})();

// Main initialization function
function initializeAll() {
    console.log("DNA Page: Initializing all components");
    
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Initialize product comparison if needed
    initProductComparison();
    
    // Initialize date range picker if available
    if (typeof $ !== 'undefined' && $.fn.daterangepicker && document.getElementById('dateRangeFilter')) {
        $('#dateRangeFilter').daterangepicker({
            opens: 'left',
            locale: {
                format: 'YYYY-MM-DD'
            },
            startDate: document.getElementById('from_date').value,
            endDate: document.getElementById('to_date').value
        }, function(start, end) {
            document.getElementById('from_date').value = start.format('YYYY-MM-DD');
            document.getElementById('to_date').value = end.format('YYYY-MM-DD');
        });
    }
    
    // Add export functionality if present
    const exportButton = document.getElementById('exportData');
    if (exportButton) {
        exportButton.addEventListener('click', function() {
            exportTableToCSV('winning-products-data.csv');
        });
    }
    
    // Add product search functionality if present
    const productSearch = document.getElementById('productSearch');
    if (productSearch) {
        productSearch.addEventListener('keyup', function() {
            searchProducts(this.value);
        });
    }
    
    // Initialize heatmap if applicable
    if (document.getElementById('productHeatmap')) {
        initializeHeatmap();
    }
    
    // Initialize View DNA buttons
    initViewDnaButtons();
    
    // Initialize filter listeners
    initializeFilterListeners();
    
    // Initialize modal functionality
    initializeModalListeners();
    
    // Initialize type badges
    initializeTypeBadges();
}

/**
 * Initialize event listeners for filter buttons
 */
function initializeFilterListeners() {
    // Get all filter buttons
    const filterBtns = document.querySelectorAll('.filter-btn');
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            console.log('Filter button clicked: ' + this.textContent);
            // No need to use localStorage - we'll handle initialization differently
        });
    });
    
    // Handle custom date form
    const customDateForm = document.getElementById('customDateForm');
    if (customDateForm) {
        customDateForm.addEventListener('submit', function() {
            console.log('Custom date form submitted');
        });
    }
}

/**
 * Initialize modal related listeners
 */
function initializeModalListeners() {
    const closeModalBtns = document.querySelectorAll('[data-dismiss="modal"]');
    const modal = document.getElementById('dnaSuggestionsModal');
    
    if (!modal) return;
    
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            modal.style.display = 'none';
            modal.classList.remove('show');
            console.log('Modal closed via button');
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
            console.log('Modal closed via outside click');
        }
    });
}

/**
 * Initialize type badge functionality
 */
function initializeTypeBadges() {
    const typeBadges = document.querySelectorAll('.type-badge');
    const reasonField = document.getElementById('reason');
    
    if (!typeBadges.length || !reasonField) return;
    
    typeBadges.forEach(badge => {
        badge.addEventListener('click', function() {
            // Toggle active class
            typeBadges.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Add reason prefix based on type
            const type = this.getAttribute('data-type');
            let reasonPrefix = '';
            
            switch(type) {
                case 'same-category':
                    reasonPrefix = 'This product is in the same category and has similar characteristics. ';
                    break;
                case 'complementary':
                    reasonPrefix = 'This product complements the winning product and can be used together. ';
                    break;
                case 'accessory':
                    reasonPrefix = 'This product is an accessory that enhances the functionality of the winning product. ';
                    break;
                case 'replacement':
                    reasonPrefix = 'This product is a replacement or spare part for the winning product. ';
                    break;
                case 'upgrade':
                    reasonPrefix = 'This product is an upgrade or premium version of the winning product. ';
                    break;
            }
            
            if (reasonField && !reasonField.value.startsWith(reasonPrefix)) {
                reasonField.value = reasonPrefix + reasonField.value;
            }
        });
    });
}

/**
 * Initialize the product comparison feature
 */
function initProductComparison() {
    const compareCheckboxes = document.querySelectorAll('.compare-checkbox');
    const compareButton = document.getElementById('compareProducts');
    
    if (!compareCheckboxes.length || !compareButton) return;
    
    // Enable/disable compare button based on selected products
    function updateCompareButton() {
        const selectedCount = document.querySelectorAll('.compare-checkbox:checked').length;
        compareButton.disabled = selectedCount < 2 || selectedCount > 4;
        compareButton.textContent = `Compare ${selectedCount} Products`;
    }
    
    // Add event listeners to all checkboxes
    compareCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateCompareButton);
    });
    
    // Initialize button state
    updateCompareButton();
    
    // Handle compare button click
    compareButton.addEventListener('click', function() {
        const selectedProducts = [];
        document.querySelectorAll('.compare-checkbox:checked').forEach(checkbox => {
            selectedProducts.push(checkbox.value);
        });
        
        if (selectedProducts.length >= 2) {
            openComparisonModal(selectedProducts);
        }
    });
}

/**
 * Open the product comparison modal
 * @param {Array} productIds - Array of product IDs to compare
 */
function openComparisonModal(productIds) {
    const modal = document.getElementById('comparisonModal');
    const modalBody = modal.querySelector('.modal-body');
    
    // Clear previous content
    modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    // Show the modal
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    // Fetch comparison data
    fetch('ajax/product_comparison.php?products=' + productIds.join(','))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                modalBody.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            // Create comparison table
            let comparisonHtml = '<div class="table-responsive"><table class="table table-bordered">';
            
            // Header row with product names
            comparisonHtml += '<thead><tr><th>Metric</th>';
            data.products.forEach(product => {
                comparisonHtml += `<th>${product.name}</th>`;
            });
            comparisonHtml += '</tr></thead><tbody>';
            
            // Add metric rows
            const metrics = [
                { key: 'sales', label: 'Total Sales (RM)' },
                { key: 'profit', label: 'Total Profit (RM)' },
                { key: 'margin', label: 'Profit Margin (%)' },
                { key: 'units', label: 'Units Sold' },
                { key: 'growth', label: 'Growth Rate (%)' }
            ];
            
            metrics.forEach(metric => {
                comparisonHtml += `<tr><td>${metric.label}</td>`;
                data.products.forEach(product => {
                    let cellValue = product[metric.key];
                    
                    // Format values based on metric type
                    if (metric.key === 'sales' || metric.key === 'profit') {
                        cellValue = Number(cellValue).toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    } else if (metric.key === 'margin' || metric.key === 'growth') {
                        const value = Number(cellValue);
                        const color = value >= 0 ? 'success' : 'danger';
                        const arrow = value >= 0 ? '↑' : '↓';
                        cellValue = `<span class="text-${color}">${arrow} ${Math.abs(value).toFixed(2)}</span>`;
                    } else {
                        cellValue = Number(cellValue).toLocaleString();
                    }
                    
                    comparisonHtml += `<td>${cellValue}</td>`;
                });
                comparisonHtml += '</tr>';
            });
            
            comparisonHtml += '</tbody></table></div>';
            
            // Add comparison chart
            comparisonHtml += '<div class="mt-4"><canvas id="comparisonChart" height="250"></canvas></div>';
            
            // Update modal content
            modalBody.innerHTML = comparisonHtml;
            
            // Initialize comparison chart
            const ctx = document.getElementById('comparisonChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.products.map(p => p.name),
                    datasets: [
                        {
                            label: 'Total Sales (RM)',
                            data: data.products.map(p => p.sales),
                            backgroundColor: 'rgba(65, 105, 225, 0.7)',
                            borderColor: 'rgba(65, 105, 225, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Total Profit (RM)',
                            data: data.products.map(p => p.profit),
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Value (RM)'
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            modalBody.innerHTML = `<div class="alert alert-danger">Error loading comparison data. Please try again.</div>`;
            console.error('Error fetching comparison data:', error);
        });
}

/**
 * Export table data to CSV file
 * @param {string} filename - Name of the CSV file
 */
function exportTableToCSV(filename) {
    const table = document.querySelector('table');
    let csv = [];
    let rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Get text content and clean it
            let data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, '').trim();
            
            // Escape double quotes and wrap in quotes if the data contains comma
            if (data.includes(',')) {
                data = '"' + data.replace(/"/g, '""') + '"';
            }
            
            row.push(data);
        }
        
        csv.push(row.join(','));
    }
    
    // Create CSV file
    const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
    const encodedUri = encodeURI(csvContent);
    
    // Create download link and trigger download
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Search products in the table
 * @param {string} query - Search query
 */
function searchProducts(query) {
    const table = document.querySelector('table');
    const rows = table.querySelectorAll('tbody tr');
    const searchTerm = query.toLowerCase();
    
    rows.forEach(row => {
        const productName = row.querySelectorAll('td')[1].textContent.toLowerCase();
        if (productName.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

/**
 * Initialize product sales heatmap
 */
function initializeHeatmap() {
    fetch('ajax/product_heatmap_data.php')
        .then(response => response.json())
        .then(data => {
            const calendarEl = document.getElementById('productHeatmap');
            
            // Format data for the heatmap
            const events = data.map(item => ({
                title: `Sales: ${item.sales}`,
                start: item.date,
                display: 'background',
                backgroundColor: getHeatmapColor(item.intensity)
            }));
            
            // Initialize FullCalendar
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: events,
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek'
                }
            });
            
            calendar.render();
        })
        .catch(error => {
            console.error('Error loading heatmap data:', error);
            document.getElementById('productHeatmap').innerHTML = 
                '<div class="alert alert-danger">Error loading heatmap data. Please try again.</div>';
        });
}

/**
 * Get color for heatmap intensity
 * @param {number} intensity - Value between 0 and 1
 * @returns {string} - RGB color string
 */
function getHeatmapColor(intensity) {
    // Generate color from blue (low) to red (high)
    const r = Math.floor(intensity * 255);
    const g = Math.floor((1 - intensity) * 100);
    const b = Math.floor((1 - intensity) * 255);
    
    return `rgba(${r}, ${g}, ${b}, 0.7)`;
}

/**
 * Calculate product recommendation score
 * @param {Object} product - Product data
 * @returns {number} - Recommendation score from 0-100
 */
function calculateRecommendationScore(product) {
    // Weights for different factors
    const weights = {
        margin: 0.3,
        growth: 0.4,
        sales: 0.2, 
        consistency: 0.1
    };
    
    // Calculate individual scores (normalized to 0-100)
    const marginScore = Math.min(100, (product.margin / 0.4) * 100); // Assume 40% is ideal
    const growthScore = Math.min(100, (product.growth / 0.3) * 100); // 30% growth gets full score
    const salesScore = Math.min(100, (product.sales / product.categoryMax) * 100);
    const consistencyScore = product.consistentGrowth ? 100 : 0;
    
    // Calculate weighted score
    return (
        (marginScore * weights.margin) +
        (growthScore * weights.growth) +
        (salesScore * weights.sales) +
        (consistencyScore * weights.consistency)
    );
}

/**
 * Add event listeners to the View DNA buttons
 * This function should be called after page load and after any DOM updates
 */
function initViewDnaButtons() {
    console.log("Initializing DNA buttons");
    const viewDnaBtns = document.querySelectorAll('.view-dna-btn');
    console.log("Found " + viewDnaBtns.length + " DNA buttons");
    
    viewDnaBtns.forEach(btn => {
        // Remove any existing event listeners to prevent duplicates
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        // Add event listener for click
        newBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent any default actions
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            showDnaSuggestions(productId, productName);
        });
    });
}

/**
 * Show DNA suggestions modal - separate function for direct calling
 */
function showDnaSuggestions(productId, productName) {
    console.log("Showing DNA suggestions for product: " + productId + " - " + productName);
    
    // Update modal title
    const modalTitle = document.getElementById('modalProductName');
    if (modalTitle) {
        modalTitle.textContent = `DNA Suggestions for: ${productName}`;
    }
    
    // Show loading spinner
    const spinner = document.getElementById('modalLoadingSpinner');
    const suggestionsArea = document.getElementById('modalDnaSuggestions');
    const noSuggestionsMsg = document.getElementById('modalNoDnaSuggestions');
    
    if (spinner) spinner.style.display = 'block';
    if (suggestionsArea) suggestionsArea.innerHTML = '';
    if (noSuggestionsMsg) noSuggestionsMsg.style.display = 'none';
    
    // Show modal
    const modal = document.getElementById('dnaSuggestionsModal');
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
    }
    
    // Fetch DNA suggestions
    fetchDnaSuggestions(productId);
}

/**
 * Function to fetch DNA suggestions
 * @param {number} productId - Product ID to fetch suggestions for
 */
function fetchDnaSuggestions(productId) {
    console.log("Fetching DNA suggestions for product ID: " + productId);
    
    // Show loading spinner
    const modalLoadingSpinner = document.getElementById('modalLoadingSpinner');
    const modalDnaSuggestions = document.getElementById('modalDnaSuggestions');
    const modalNoDnaSuggestions = document.getElementById('modalNoDnaSuggestions');
    
    if (modalLoadingSpinner) modalLoadingSpinner.style.display = 'block';
    if (modalDnaSuggestions) modalDnaSuggestions.innerHTML = '';
    if (modalNoDnaSuggestions) modalNoDnaSuggestions.style.display = 'none';
    
    // Add timestamp parameter to prevent caching
    fetch(`get_dna_suggestions.php?product_id=${productId}&_=${Date.now()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(suggestions => {
            console.log("Received suggestions:", suggestions);
            
            if (modalLoadingSpinner) modalLoadingSpinner.style.display = 'none';
            
            // Handle error response
            if (suggestions && suggestions.error) {
                console.error("Error in suggestions:", suggestions.error);
                if (modalNoDnaSuggestions) {
                    modalNoDnaSuggestions.textContent = suggestions.error;
                    modalNoDnaSuggestions.style.display = 'block';
                }
                return;
            }
            
            // Check if suggestions is an array and has items
            if (Array.isArray(suggestions) && suggestions.length > 0) {
                let suggestionHtml = '<div class="row">';
                
                suggestions.forEach(suggestion => {
                    suggestionHtml += `
                        <div class="col-md-6 mb-3">
                            <div class="dna-suggestion-card">
                                <div class="dna-suggestion-header">
                                    <span>${suggestion.suggested_product_name}</span>
                                </div>
                                <div class="dna-suggestion-body">
                                    <p>${suggestion.reason}</p>
                                    <div class="dna-suggestion-meta">
                                        <span><i class="fas fa-user"></i> ${suggestion.added_by_name || 'Admin'}</span>
                                        <span><i class="fas fa-calendar"></i> ${new Date(suggestion.created_at).toLocaleDateString()}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                suggestionHtml += '</div>';
                if (modalDnaSuggestions) modalDnaSuggestions.innerHTML = suggestionHtml;
            } else {
                console.log("No suggestions found");
                if (modalNoDnaSuggestions) modalNoDnaSuggestions.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error fetching DNA suggestions:', error);
            if (modalLoadingSpinner) modalLoadingSpinner.style.display = 'none';
            if (modalNoDnaSuggestions) {
                modalNoDnaSuggestions.textContent = 'Error loading suggestions. Please try again.';
                modalNoDnaSuggestions.style.display = 'block';
            }
        });
}
