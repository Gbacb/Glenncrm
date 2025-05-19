/**
 * Main JavaScript for Glenn CRM
 */

document.addEventListener('DOMContentLoaded', function() {
    // Enable Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-disappearing alerts
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Date picker initialization for date inputs
    var dateInputs = document.querySelectorAll('.datepicker');
    dateInputs.forEach(function(input) {
        input.addEventListener('focus', function() {
            this.type = 'date';
        });
        input.addEventListener('blur', function() {
            if (!this.value) {
                this.type = 'text';
            }
        });
    });
    
    // Confirmation for delete actions
    document.querySelectorAll('.confirm-delete').forEach(function(element) {
        element.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Dynamic form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

/**
 * Dashboard chart initialization
 */
function initDashboardCharts(salesData, leadsData) {
    // Sales Chart
    if (document.getElementById('salesChart')) {
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'Sales',
                    data: salesData.values,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Leads Chart
    if (document.getElementById('leadsChart')) {
        const ctx = document.getElementById('leadsChart').getContext('2d');
        const leadsChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: leadsData.labels,
                datasets: [{
                    data: leadsData.values,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(201, 203, 207, 0.7)'
                    ],
                    borderColor: 'white',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

/**
 * Customer search functionality
 */
function searchCustomers() {
    const input = document.getElementById('customerSearch');
    const filter = input.value.toUpperCase();
    const table = document.querySelector('.customer-table');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) { // Start from 1 to skip header
        const nameColumn = rows[i].getElementsByTagName('td')[1]; // Name is in the second column
        
        if (nameColumn) {
            const textValue = nameColumn.textContent || nameColumn.innerText;
            
            if (textValue.toUpperCase().indexOf(filter) > -1) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }
}

/**
 * Dynamic dependent dropdowns (e.g., select customer to populate associated leads)
 */
function loadCustomerLeads(customerId, targetSelectId) {
    const leadSelect = document.getElementById(targetSelectId);
    
    // Clear existing options
    leadSelect.innerHTML = '<option value="">-- Select Lead --</option>';
    
    if (!customerId) return;
    
    // AJAX request to get leads for the selected customer
    fetch('includes/ajax_handlers.php?action=get_customer_leads&customer_id=' + customerId)
        .then(response => response.json())
        .then(data => {
            data.forEach(lead => {
                const option = document.createElement('option');
                option.value = lead.lead_id;
                option.textContent = lead.title;
                leadSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading leads:', error));
}