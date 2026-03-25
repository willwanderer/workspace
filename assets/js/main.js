/**
 * WorkSpace Pro - Main JavaScript
 * Interactive Dashboard Features
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initSidebar();
    initDropdowns();
    initModals();
    initSearch();
    initFormValidation();
    initTooltips();
    initConfirmDialogs();
    initTaskChecklist();
    initCharts();
    initQuickActions();
    initThemeToggle();
    initDragAndDrop();
    initSwalConfirm();
});

/* ============================================
   SWEETALERT CONFIRM HELPERS
   ============================================ */
function initSwalConfirm() {
    // Handle all forms with data-confirm-swal attribute
    document.querySelectorAll('form[data-confirm-swal]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = form.dataset.swalTitle || 'Are you sure?';
            const text = form.dataset.swalText || '';
            const confirmText = form.dataset.swalConfirm || 'Yes';
            const cancelText = form.dataset.swalCancel || 'Cancel';
            const icon = form.dataset.swalIcon || 'question';
            
            const result = await Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: cancelText,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280'
            });
            
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
}

function swalConfirm(title, text, icon = 'question') {
    return Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonText: 'Ya',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        allowOutsideClick: false,
        allowEscapeKey: false,
        backdrop: true
    });
}

function swalSuccess(title, text) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'success',
        confirmButtonColor: '#3b82f6'
    });
}

function swalError(title, text) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'error',
        confirmButtonColor: '#3b82f6'
    });
}

/* ============================================
   SIDEBAR
   ============================================ */
function initSidebar() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }
    
    // Auto-collapse sidebar on mobile
    if (window.innerWidth <= 768) {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                sidebar.classList.remove('open');
            });
        });
    }
}

/* ============================================
   DROPDOWNS
   ============================================ */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.dropdown-trigger');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (trigger && menu) {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                closeAllDropdowns();
                dropdown.classList.toggle('active');
            });
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
        closeAllDropdowns();
    });
    
    function closeAllDropdowns() {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    }
}

/* ============================================
   MODALS
   ============================================ */
function initModals() {
    // Open modal with data attribute
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const modalId = trigger.dataset.modal;
            openModal(modalId);
        });
    });
    
    // Close modal with close button
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            closeModal(btn.closest('.modal').id);
        });
    });
    
    // Close modal with backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', () => {
            closeAllModals();
        });
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    const backdrop = document.querySelector('.modal-backdrop');
    
    if (modal) {
        modal.classList.add('active');
        if (backdrop) backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    const backdrop = document.querySelector('.modal-backdrop');
    
    if (modal) {
        modal.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.classList.remove('active');
    });
    document.body.style.overflow = '';
}

/* ============================================
   SEARCH
   ============================================ */
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    const searchForm = document.getElementById('globalSearchForm');
    const suggestionsBox = document.getElementById('searchSuggestions');
    
    if (searchInput && suggestionsBox) {
        let searchTimeout;
        let selectedIndex = -1;
        let currentQuery = '';
        
        // Function to fetch and display suggestions
        const doSearch = (query) => {
            currentQuery = query;
            selectedIndex = -1;
            
            if (query.length < 2) {
                suggestionsBox.style.display = 'none';
                suggestionsBox.innerHTML = '';
                return;
            }
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetchSearchSuggestions(query);
            }, 200);
        };
        
        // Handle input event (typing)
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            doSearch(query);
        });
        
        // Handle keyboard events - show suggestions on ArrowUp/ArrowDown
        searchInput.addEventListener('keydown', (e) => {
            const items = suggestionsBox.querySelectorAll('.search-suggestion-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                // If suggestions are hidden and there's a query, show them first
                if (suggestionsBox.style.display === 'none' && searchInput.value.trim().length >= 2) {
                    doSearch(searchInput.value.trim());
                    return;
                }
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelectedSuggestion(items, selectedIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                // If suggestions are hidden and there's a query, show them first
                if (suggestionsBox.style.display === 'none' && searchInput.value.trim().length >= 2) {
                    doSearch(searchInput.value.trim());
                    return;
                }
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelectedSuggestion(items, selectedIndex);
            } else if (e.key === 'Enter') {
                // If a suggestion is selected, go to that URL
                if (selectedIndex >= 0 && items[selectedIndex]) {
                    e.preventDefault();
                    const url = items[selectedIndex].getAttribute('data-url');
                    if (url) {
                        window.location.href = url;
                        return;
                    }
                }
                // Otherwise, submit the form to go to search results
                searchForm.submit();
            } else if (e.key === 'Escape') {
                suggestionsBox.style.display = 'none';
                selectedIndex = -1;
            }
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.style.display = 'none';
            }
        });
        
        // Show suggestions when input is focused and has value
        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 2) {
                fetchSearchSuggestions(searchInput.value.trim());
            }
        });
    }
}

function updateSelectedSuggestion(items, selectedIndex) {
    items.forEach((item, index) => {
        item.classList.toggle('search-suggestion-selected', index === selectedIndex);
    });
}

function fetchSearchSuggestions(query) {
    const suggestionsBox = document.getElementById('searchSuggestions');
    
    fetch('api/search_suggestions.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                let html = '';
                data.forEach((item, index) => {
                    const targetAttr = item.external ? 'target="_blank" rel="noopener"' : '';
                    html += `
                        <a href="${item.url}" class="search-suggestion-item" data-url="${item.url}" data-index="${index}" ${targetAttr}>
                            <span class="search-suggestion-icon">${item.icon}</span>
                            <div class="search-suggestion-content">
                                <div class="search-suggestion-title">${escapeHtml(item.title)}</div>
                                <div class="search-suggestion-subtitle">${escapeHtml(item.subtitle)}</div>
                            </div>
                        </a>
                    `;
                });
                // Add "View all results" option
                html += `
                    <a href="index.php?page=search&q=${encodeURIComponent(query)}" class="search-suggestion-all">
                        View all results for "${escapeHtml(query)}" →
                    </a>
                `;
                suggestionsBox.innerHTML = html;
                suggestionsBox.style.display = 'block';
            } else {
                suggestionsBox.innerHTML = '<div class="search-suggestion-empty">No results found</div>';
                suggestionsBox.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            suggestionsBox.style.display = 'none';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/* ============================================
   FORM VALIDATION
   ============================================ */
function initFormValidation() {
    const forms = document.querySelectorAll('.ajax-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            
            // Disable button and show loading
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="animate-pulse">Loading...</span>';
            }
            
            try {
                const formData = new FormData(form);
                const response = await fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message || 'Success!', 'success');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                    if (data.reset) {
                        form.reset();
                    }
                } else {
                    showAlert(data.message || 'An error occurred', 'error');
                }
            } catch (error) {
                showAlert('Network error. Please try again.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    });
}

/* ============================================
   ALERTS
   ============================================ */
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} animate-slide-up`;
    alertDiv.innerHTML = `
        <span>${type === 'success' ? '✓' : '⚠'}</span>
        <span>${message}</span>
    `;
    
    const container = document.querySelector('.content-wrapper') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

/* ============================================
   TOOLTIPS
   ============================================ */
function initTooltips() {
    const tooltipTriggers = document.querySelectorAll('[data-tooltip]');
    
    tooltipTriggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', () => {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = trigger.dataset.tooltip;
            document.body.appendChild(tooltip);
            
            const rect = trigger.getBoundingClientRect();
            tooltip.style.cssText = `
                position: fixed;
                top: ${rect.top - tooltip.offsetHeight - 8}px;
                left: ${rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)}px;
                background: #1e293b;
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 10000;
                white-space: nowrap;
            `;
            
            trigger._tooltip = tooltip;
        });
        
        trigger.addEventListener('mouseleave', () => {
            if (trigger._tooltip) {
                trigger._tooltip.remove();
                trigger._tooltip = null;
            }
        });
    });
}

/* ============================================
   CONFIRM DIALOGS
   ============================================ */
function initConfirmDialogs() {
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    
    confirmButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const message = btn.dataset.confirm || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/* ============================================
   TASK CHECKLIST
   ============================================ */
function initTaskChecklist() {
    const checkboxes = document.querySelectorAll('.task-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', async () => {
            const taskId = checkbox.dataset.taskId;
            const isChecked = checkbox.checked;
            
            // Visual feedback
            const taskItem = checkbox.closest('.task-item');
            if (taskItem) {
                taskItem.classList.toggle('completed', isChecked);
            }
            
            // In a real app, this would be an AJAX call
            if (taskId) {
                console.log('Task', taskId, isChecked ? 'completed' : 'uncompleted');
                
                // Update task status via AJAX
                try {
                    const formData = new FormData();
                    formData.append('action', 'toggle_task');
                    formData.append('task_id', taskId);
                    formData.append('status', isChecked ? 'completed' : 'pending');
                    
                    await fetch('api/tasks.php', {
                        method: 'POST',
                        body: formData
                    });
                } catch (error) {
                    console.error('Error updating task:', error);
                }
            }
        });
    });
}

/* ============================================
   CHARTS
   ============================================ */
function initCharts() {
    // Task Status Chart
    const statusChart = document.getElementById('taskStatusChart');
    if (statusChart && typeof Chart !== 'undefined') {
        new Chart(statusChart, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed', 'Cancelled'],
                datasets: [{
                    data: [
                        statusChart.dataset.pending || 5,
                        statusChart.dataset.inProgress || 3,
                        statusChart.dataset.completed || 8,
                        statusChart.dataset.cancelled || 1
                    ],
                    backgroundColor: ['#6b7280', '#3b82f6', '#10b981', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Activity Chart
    const activityChart = document.getElementById('activityChart');
    if (activityChart && typeof Chart !== 'undefined') {
        // Use database data if available, otherwise use default
        const activityData = (typeof weeklyActivityData !== 'undefined') ? weeklyActivityData : [0, 0, 0, 0, 0, 0, 0];
        const activityLabels = (typeof weeklyActivityLabels !== 'undefined') ? weeklyActivityLabels : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        new Chart(activityChart, {
            type: 'bar',
            data: {
                labels: activityLabels,
                datasets: [{
                    label: 'Activities',
                    data: activityData,
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Project Progress Chart
    const projectChart = document.getElementById('projectProgressChart');
    if (projectChart && typeof Chart !== 'undefined') {
        new Chart(projectChart, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Progress',
                    data: [20, 35, 55, 75],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
}

/* ============================================
   QUICK ACTIONS
   ============================================ */
function initQuickActions() {
    const quickActionBtns = document.querySelectorAll('[data-quick-action]');
    
    quickActionBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.dataset.quickAction;
            const modalId = btn.dataset.modal;
            
            if (modalId) {
                openModal(modalId);
            }
        });
    });
}

/* ============================================
   THEME TOGGLE
   ============================================ */
function initThemeToggle() {
    const themeToggle = document.querySelector('[data-theme-toggle]');
    
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.dataset.theme;
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.dataset.theme = newTheme;
            localStorage.setItem('theme', newTheme);
            
            // Save preference to server
            fetch('api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_theme&theme=${newTheme}`
            });
        });
    }
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.documentElement.dataset.theme = savedTheme;
    }
}

/* ============================================
   NOTIFICATIONS PANEL
   ============================================ */
function loadNotifications() {
    const container = document.getElementById('notificationsList');
    if (!container) return;
    
    // Fetch notifications via AJAX
    fetch('api/notifications.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                container.innerHTML = data.notifications.map(notif => `
                    <div class="notification-item ${notif.is_read ? '' : 'unread'}">
                        <div class="notification-icon">
                            ${getNotificationIcon(notif.type)}
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${notif.title}</div>
                            <div class="notification-message">${notif.message || ''}</div>
                            <div class="notification-time">${timeAgo(notif.created_at)}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<div class="p-4 text-center text-muted">No notifications</div>';
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

function getNotificationIcon(type) {
    const icons = {
        'task_due': '⏰',
        'task_assigned': '✅',
        'project_update': '📁',
        'comment': '💬',
        'mention': '📢',
        'system': '⚙️'
    };
    return icons[type] || '🔔';
}

function timeAgo(date) {
    const now = new Date();
    const past = new Date(date);
    const diff = Math.floor((now - past) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    
    return past.toLocaleDateString();
}

/* ============================================
   AJAX HELPERS
   ============================================ */
function ajax(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {},
        body: null
    };
    
    const config = { ...defaults, ...options };
    
    return fetch(url, {
        method: config.method,
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            ...config.headers
        },
        body: config.body
    }).then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    });
}

/* ============================================
   FILTER FUNCTIONS
   ============================================ */
function filterTasks(filters) {
    const taskItems = document.querySelectorAll('.task-item');
    
    taskItems.forEach(item => {
        let show = true;
        
        // Filter by status
        if (filters.status && filters.status !== 'all') {
            show = show && item.dataset.status === filters.status;
        }
        
        // Filter by priority
        if (filters.priority && filters.priority !== 'all') {
            show = show && item.dataset.priority === filters.priority;
        }
        
        // Filter by search
        if (filters.search) {
            const title = item.querySelector('.task-title')?.textContent.toLowerCase() || '';
            show = show && title.includes(filters.search.toLowerCase());
        }
        
        item.style.display = show ? '' : 'none';
    });
}

/* ============================================
   FILE PREVIEW
   ============================================ */
function handleFileSelect(input, previewContainer) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const container = document.getElementById(previewContainer);
    
    if (!container) return;
    
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            container.innerHTML = `
                <div class="file-preview">
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="btn-remove" onclick="this.parentElement.remove()">×</button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        container.innerHTML = `
            <div class="file-preview file-icon">
                <span class="file-icon-text">${file.name}</span>
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()">×</button>
            </div>
        `;
    }
}

/* ============================================
   EXPORT DATA
   ============================================ */
function exportToCSV(data, filename) {
    const headers = Object.keys(data[0] || {});
    const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(header => 
            JSON.stringify(row[header] || '')
        ).join(','))
    ].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

/* ============================================
   DEBOUNCE
   ============================================ */
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

/* ============================================
   ANIMATE NUMBERS
   ============================================ */
function animateNumber(element, target, duration = 1000) {
    const start = 0;
    const startTime = performance.now();
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const easeOutQuad = 1 - (1 - progress) * (1 - progress);
        const current = Math.floor(start + (target - start) * easeOutQuad);
        
        element.textContent = current;
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    
    requestAnimationFrame(update);
}

/* ============================================
   STICKY HEADER
   ============================================ */
let lastScrollTop = 0;
window.addEventListener('scroll', debounce(() => {
    const header = document.querySelector('.header');
    if (!header) return;
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (scrollTop > lastScrollTop && scrollTop > 100) {
        header.classList.add('hidden');
    } else {
        header.classList.remove('hidden');
    }
    
    lastScrollTop = scrollTop;
}, 10));

/* ============================================
    INITIALIZE NOTIFICATIONS
    ============================================ */
// Load notifications on page load if the container exists
if (document.getElementById('notificationsList')) {
    loadNotifications();
}

/* ============================================
   DRAG AND DROP - FILES & FOLDERS
   ============================================ */
let draggedItem = null;
let draggedType = null;
let currentFolderId = null;

function initDragAndDrop() {
    // Global drag events for the document
    document.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (e.dataTransfer.types.includes('Files')) {
            showGlobalDropZone();
        }
    });
    
    document.addEventListener('dragleave', (e) => {
        // Only hide if leaving the document
        if (e.relatedTarget === null || e.relatedTarget === document.documentElement) {
            hideGlobalDropZone();
        }
    });
    
    document.addEventListener('drop', (e) => {
        e.preventDefault();
        hideGlobalDropZone();
        
        // Handle file drop from OS
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleFileDrop(e.dataTransfer.files);
        }
    });
    
    // Initialize folder drop zones
    initFolderDropZones();
}

function initFolderDropZones() {
    const folderDropZones = document.querySelectorAll('.folder-drop-zone');
    
    folderDropZones.forEach(zone => {
        zone.addEventListener('dragover', handleDragOver);
        zone.addEventListener('dragleave', handleDragLeave);
        zone.addEventListener('drop', (e) => {
            const folderId = zone.dataset.folderId;
            handleDrop(e, folderId);
        });
    });
}

function handleDragStart(e, itemId, type) {
    draggedItem = itemId;
    draggedType = type;
    e.dataTransfer.effectAllowed = 'move';
    e.target.style.opacity = '0.5';
    
    // Reset opacity on drag end
    e.target.addEventListener('dragend', () => {
        e.target.style.opacity = '1';
    }, { once: true });
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    
    if (e.currentTarget.classList.contains('folder-drop-zone')) {
        e.currentTarget.style.borderColor = 'var(--primary)';
        e.currentTarget.style.background = 'var(--bg-hover)';
    }
}

function handleDragLeave(e) {
    if (e.currentTarget.classList.contains('folder-drop-zone')) {
        e.currentTarget.style.borderColor = 'var(--border-light)';
        e.currentTarget.style.background = 'var(--bg-secondary)';
    }
}

function handleDrop(e, folderId) {
    e.preventDefault();
    
    // Reset styles
    if (e.currentTarget.classList.contains('folder-drop-zone')) {
        e.currentTarget.style.borderColor = 'var(--border-light)';
        e.currentTarget.style.background = 'var(--bg-secondary)';
    }
    
    // If dragging a file to a folder, update the folder
    if (draggedType === 'file' && draggedItem) {
        moveFileToFolder(draggedItem, folderId);
    }
    
    draggedItem = null;
    draggedType = null;
}

function handleFileDrop(files) {
    // Create form data and submit
    const formData = new FormData();
    formData.append('action', 'upload_file');
    formData.append('folder_id', currentFolderId || '');
    
    for (let i = 0; i < files.length; i++) {
        formData.append('file', files[i]);
    }
    
    // Submit via fetch
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        window.location.reload();
    })
    .catch(error => {
        console.error('Upload failed:', error);
        showAlert('Failed to upload files', 'error');
    });
}

function showGlobalDropZone() {
    const dropZone = document.getElementById('globalDropZone');
    if (dropZone) {
        dropZone.style.display = 'flex';
    }
}

function hideGlobalDropZone() {
    const dropZone = document.getElementById('globalDropZone');
    if (dropZone) {
        dropZone.style.display = 'none';
    }
}

function moveFileToFolder(fileId, folderId) {
    fetch('api/update_file_folder.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'file_id=' + encodeURIComponent(fileId) + '&folder_id=' + encodeURIComponent(folderId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('File moved successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showAlert(data.message || 'Failed to move file', 'error');
        }
    })
    .catch(error => {
        console.error('Move failed:', error);
        showAlert('Failed to move file', 'error');
    });
}

function submitUpload(input) {
    if (input.files && input.files.length > 0) {
        const form = document.getElementById('uploadForm');
        const folderIdInput = document.getElementById('uploadFolderId');
        
        if (currentFolderId) {
            folderIdInput.value = currentFolderId;
        }
        
        // Submit the form
        const formData = new FormData(form);
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            window.location.reload();
        })
        .catch(error => {
            console.error('Upload failed:', error);
            showAlert('Failed to upload file', 'error');
        });
    }
}
