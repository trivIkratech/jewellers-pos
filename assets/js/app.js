/**
 * Jewellery ERP - Main Application JavaScript
 */

const BASE_URL = window.location.pathname.startsWith('/POS-Jewellery') ? '/POS-Jewellery' : '';

// ---- Sidebar Toggle ----
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
    
    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Initialize tooltips
    const tooltipList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipList.forEach(function(el) {
        new bootstrap.Tooltip(el);
    });
    
    // Load notifications
    loadNotifications();
    
    // Mark all read
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllNotificationsRead();
        });
    }
});

// ---- AJAX Helper ----
function ajaxRequest(url, method, data, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    if (method === 'POST' && !(data instanceof FormData)) {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            try {
                const response = JSON.parse(xhr.responseText);
                callback(null, response);
            } catch(e) {
                callback(e, xhr.responseText);
            }
        }
    };
    
    if (method === 'POST' && data && !(data instanceof FormData)) {
        const params = new URLSearchParams(data).toString();
        xhr.send(params);
    } else if (data instanceof FormData) {
        xhr.send(data);
    } else {
        xhr.send();
    }
}

// ---- Notifications ----
function loadNotifications() {
    ajaxRequest(BASE_URL + '/modules/dashboard/ajax/notifications.php', 'GET', null, function(err, data) {
        if (!err && data.notifications) {
            const container = document.getElementById('notificationList');
            if (!container) return;
            
            if (data.notifications.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-bell-slash fs-4 d-block mb-2"></i><small>No new notifications</small></div>';
                return;
            }
            
            let html = '';
            data.notifications.forEach(function(notif) {
                const iconClass = notif.type === 'warning' ? 'bi-exclamation-triangle text-warning bg-warning-light' :
                                  notif.type === 'danger' ? 'bi-exclamation-circle text-danger bg-danger-light' :
                                  notif.type === 'success' ? 'bi-check-circle text-success bg-success-light' :
                                  'bi-info-circle text-primary bg-primary-light';
                html += '<div class="notification-item ' + (notif.is_read ? '' : 'unread') + '">' +
                        '<div class="notif-icon ' + iconClass + '"><i class="bi ' + iconClass.split(' ')[0] + '"></i></div>' +
                        '<div class="notif-content"><p>' + notif.title + '</p>' +
                        '<div class="time">' + notif.time_ago + '</div></div></div>';
            });
            container.innerHTML = html;
        }
    });
}

function markAllNotificationsRead() {
    ajaxRequest(BASE_URL + '/modules/dashboard/ajax/notifications.php?action=mark_read', 'POST', {}, function(err, data) {
        if (!err) {
            loadNotifications();
            const badge = document.querySelector('.badge-dot');
            if (badge) badge.remove();
        }
    });
}

// ---- Confirm Delete ----
function confirmDelete(message, url) {
    if (confirm(message || 'Are you sure you want to delete this item?')) {
        window.location.href = url;
    }
    return false;
}

// ---- Format Currency ----
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ---- Format Weight ----
function formatWeight(weight) {
    return parseFloat(weight).toFixed(3) + ' g';
}

// ---- Debounce ----
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ---- Show Toast ----
function showToast(message, type) {
    type = type || 'success';
    const toastHtml = '<div class="position-fixed bottom-0 end-0 p-3" style="z-index:11">' +
        '<div class="toast show align-items-center text-bg-' + type + ' border-0" role="alert">' +
        '<div class="d-flex"><div class="toast-body">' + message + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
        '</div></div></div>';
    
    const container = document.createElement('div');
    container.innerHTML = toastHtml;
    document.body.appendChild(container);
    
    setTimeout(function() {
        container.remove();
    }, 4000);
}

// ---- Print Section ----
function printSection(elementId) {
    const content = document.getElementById(elementId);
    if (!content) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Print</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<link href="/POS-Jewellery/assets/css/style.css" rel="stylesheet">');
    printWindow.document.write('<link href="/POS-Jewellery/assets/css/invoice.css" rel="stylesheet">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    printWindow.onload = function() {
        printWindow.print();
    };
}

// ---- Number to Words (Indian) ----
function numberToWords(num) {
    if (num === 0) return 'Zero Rupees Only';
    
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                  'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
                  'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
    let decimal = Math.round((num - Math.floor(num)) * 100);
    num = Math.floor(num);
    let words = '';
    
    function helper(n) {
        let w = '';
        if (n >= 100) {
            w += ones[Math.floor(n/100)] + ' Hundred ';
            n %= 100;
        }
        if (n >= 20) {
            w += tens[Math.floor(n/10)];
            if (n % 10 > 0) w += ' ' + ones[n % 10];
        } else if (n > 0) {
            w += ones[n];
        }
        return w.trim();
    }
    
    if (num >= 10000000) { words += helper(Math.floor(num / 10000000)) + ' Crore '; num %= 10000000; }
    if (num >= 100000) { words += helper(Math.floor(num / 100000)) + ' Lakh '; num %= 100000; }
    if (num >= 1000) { words += helper(Math.floor(num / 1000)) + ' Thousand '; num %= 1000; }
    if (num > 0) {
        if (words) words += 'and ';
        words += helper(num);
    }
    
    words = words.trim() + ' Rupees';
    if (decimal > 0) {
        words += ' and ' + helper(decimal) + ' Paise';
    }
    words += ' Only';
    return words;
}

