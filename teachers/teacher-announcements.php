<?php
// Use a separate session name for teachers - MUST be first
$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Redirect to login if not logged in as teacher
if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    header('Location: teacher-login.php');
    exit;
}

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>

    <link rel="stylesheet" href="teacher.css" />
    <link rel="stylesheet" href="announcement.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
        
</head>
<body>
    <!--Top Navbar-->
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="navbar-logo">
                <img src="g2flogo.png" class="logo-image"/>
            </div>
            <div class="navbar-text">
                <div class="navbar-title">Glorious God's Family</div>
                <div class="navbar-subtitle">Christian School</div>
            </div>
        </div>
        <div class="navbar-actions">
            <div class="user-menu">
                <span><?php echo $user_name; ?></span>
                <a href="teacher-logout.php" class="logout-btn" title="Logout">
                    <button type="button" style="background: none; border: none; padding: 8px 16px; color: #fff; cursor: pointer;  transition: background-color 0.3s ease;">
            <img src="logout-btn.png" alt="Logout" style="width:30px; height:30px; vertical-align: middle; margin-right: 8px;">
          </button>
                </a>
            </div>
        </div>
    </nav>

    <!--Main Page Container-->
    <div class="page-wrapper">
        <!--Sidebar-->
        <aside class="side">
            <nav class="nav">
                <a href="teacher.php">Dashboard</a>
                <a href="tprofile.php">Profile</a>
                <a href="student_schedule.php">Schedule</a>        
                
                <a href="listofstudents.php">Lists of students</a>
                <a href="grades.php">Grades</a>
                <a href="school_calendar.php">School Calendar</a>
                <a href="teacher-announcements.php" class="active">Announcements</a>
                <a href="teacherslist.php">Teachers</a>
                <a href="teacher-settings.php">Settings</a>
            </nav>
            <div class="side-foot">Logged in as <strong>Teacher</strong></div>
        </aside>
        
        <!--Main Content-->
        <main class="main">
            <header class="header">
                <h1>School Announcements</h1>
            </header>

            <!--Announcement content-->
            <section class="announcement-container">
                <h2 class="page-title">Latest Update</h2>

                <!-- Filter bar -->
                <div class="filter-bar">
                    <button type="button" id="filter-toggle" class="filter-toggle">Filter</button>
                </div>
                <div id="filter-panel" class="filter-panel" hidden>
                    <div class="filter-row">
                        <label for="filter-text">Search</label>
                        <input id="filter-text" type="text" placeholder="Search title or content" />
                    </div>
                    <div class="filter-row">
                        <label for="filter-tag">Tag</label>
                        <select id="filter-tag">
                            <option value="">All</option>
                            <optgroup label="Category">
                                <option value="important">Important</option>
                                <option value="new">New</option>
                                <option value="info">Info</option>
                            </optgroup>
                            <optgroup label="Visibility">
                                <option value="students">Students Only</option>
                                <option value="teachers">Teachers Only</option>
                                <option value="both">Both</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="filter-row">
                        <label for="filter-from">From</label>
                        <input id="filter-from" type="date" />
                    </div>
                    <div class="filter-row">
                        <label for="filter-to">To</label>
                        <input id="filter-to" type="date" />
                    </div>
                    <div class="filter-actions">
                        <button type="button" id="filter-apply">Apply</button>
                        <button type="button" id="filter-reset">Reset</button>
                    </div>
                </div>

                <ul id="announcement-list" class="announcement-list">
                    <!--announcement item 1 (new feature)-->
                    <li class="loading-message">Loading announcements...</li>
                </ul>
            </section>

            <button type="button" id="add-announcement-btn" class="announcement-button" onclick="showModal()">+</button>
        </main>
        
        <div id="announcement-modal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="hideModal()">&times;</span>
                <h2>Create New Announcement</h2>
                <form id="new-announcement-form">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" required>
                    </div>
                    <div class="form-group">
                        <label for="tag">Tag/Category</label>
                        <select id="tag" required>
                            <option value="info">General Info</option>
                            <option value="important">Important</option>
                            <option value="new">New/Update</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="visibility">Visibility</label>
                        <select id="visibility" required>
                            <option value="both">Both Students & Teachers</option>
                            <option value="students">Students Only</option>
                            <option value="teachers">Teachers Only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date">Display Date (optional)</label>
                        <input type="date" id="date">
                    </div>
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea id="content" required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" onclick="hideModal()">Cancel</button>
                        <button type="submit" id="submit-btn">Post Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal handling functions
        function showModal() {
            document.getElementById('announcement-modal').style.display = 'flex';
        }
    
        function hideModal() {
            document.getElementById('announcement-modal').style.display = 'none';
        }
    
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('announcement-modal');
            if (event.target === modal) {
                hideModal();
            }
        }

        function isHtmlLoginResponse(text) {
            if (!text) return false;
            const low = text.toLowerCase();
            // crude detection: either an HTML document or mentions of 'login' or a form
            return low.trim().startsWith('<') || low.includes('<!doctype') || low.includes('<html') || low.includes('login') || low.includes('<form');
        }

        // Load announcements from API - robust detection of non-JSON responses
        function loadAnnouncements() {
            fetch('../api/announcements.php?action=list', { credentials: 'same-origin' })
                .then(res => res.text())
                .then(text => {
                    if (isHtmlLoginResponse(text)) {
                        // If it looks like a redirect to login or HTML, navigate to teacher login
                        alert('Session expired or server returned a login page. Redirecting to login.');
                        window.location.href = 'teacher-login.php';
                        return;
                    }
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Unexpected server response (not JSON):', text);
                        document.getElementById('announcement-list').innerHTML =
                            '<li class="loading-message">Unable to load announcements. Server returned an unexpected response.</li>';
                        return;
                    }
                    
                    const list = document.getElementById('announcement-list');
                    list.innerHTML = '';
                    
                    if (!data.success || !data.announcements || data.announcements.length === 0) {
                        list.innerHTML = '<li class="loading-message">No announcements at this time.</li>';
                        return;
                    }
                    
                    data.announcements.forEach(ann => {
                        if (!ann.title || ann.title.trim() === '') return;
                        
                        const li = document.createElement('li');
                        li.className = 'announcement-item';
                        li.setAttribute('data-id', ann.id);
                        li.style.cssText = 'padding:12px;margin:8px 0;background:#f9f9f9;border-left:4px solid #1a73e8;border-radius:4px;';
                        
                        const icon = ann.type === 'event' ? '📅' : '📢';
                        const visibilityBadge = ann.visibility === 'students' ? '<span style="background:#e3f2fd;color:#1a73e8;padding:2px 6px;border-radius:3px;font-size:11px;margin-left:8px;">Students Only</span>' 
                                             : ann.visibility === 'teachers' ? '<span style="background:#f3e5f5;color:#7b1fa2;padding:2px 6px;border-radius:3px;font-size:11px;margin-left:8px;">Teachers Only</span>'
                                             : '<span style="background:#e8f5e9;color:#388e3c;padding:2px 6px;border-radius:3px;font-size:11px;margin-left:8px;">Both</span>';
                        const date = ann.pub_date && ann.pub_date.trim() ? escapeHtml(ann.pub_date) : 'Today';
                        const title = ann.title ? escapeHtml(ann.title) : 'Untitled';
                        
                        li.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                            <strong>${icon} ${title}</strong>
                                            <div>
                                                ${visibilityBadge}
                                                <button type="button" class="delete-btn" data-id="${ann.id}" style="background:#ff6b6b;color:white;border:none;padding:4px 8px;border-radius:3px;font-size:11px;cursor:pointer;margin-left:8px;">Delete</button>
                                            </div>
                                        </div>
                                        <div style="font-size:12px;color:#666;">${date}</div>`;
                        list.appendChild(li);
                    });
                    
                    if (list.children.length === 0) {
                        list.innerHTML = '<li class="loading-message">No announcements at this time.</li>';
                        return;
                    }
                    
                    // Attach delete button event listeners AFTER elements are added to DOM
                    document.querySelectorAll('.delete-btn').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const id = this.getAttribute('data-id');
                            if (confirm('Are you sure you want to delete this announcement?')) {
                                deleteAnnouncement(id);
                            }
                        });
                    });
                })
                .catch(err => {
                    console.error('Error loading announcements:', err);
                    document.getElementById('announcement-list').innerHTML = '<li class="loading-message">Error loading announcements.</li>';
                });
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Delete announcement function - use POST + override; parse server text then JSON
        function deleteAnnouncement(id) {
            fetch('../api/announcements.php?action=delete', {
                method: 'POST', // use POST and X-HTTP-Method-Override so services blocking DELETE still accept it
                headers: {
                    'Content-Type': 'application/json',
                    'X-HTTP-Method-Override': 'DELETE'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ id: parseInt(id) })
            })
                .then(res => res.text())
                .then(text => {
                    if (isHtmlLoginResponse(text)) {
                        alert('Session expired or server returned a login page. Redirecting to login.');
                        window.location.href = 'teacher-login.php';
                        return;
                    }
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        // Possibly HTML error page or redirect - show message
                        const snippet = text && text.length > 200 ? text.substring(0, 200) + '...' : text;
                        alert('Unexpected server response. Please check your session or server logs. (Server returned HTML or non-JSON content)\n\n' + snippet);
                        console.error('Unexpected server response (not JSON):', text);
                        return;
                    }
                    if (result.success) {
                        alert('Announcement deleted successfully!');
                        loadAnnouncements();
                    } else {
                        alert('Error: ' + (result.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    alert('Error deleting announcement: ' + err.message);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Update year in footer
            var yearEl = document.getElementById('year');
            if (yearEl) yearEl.textContent = new Date().getFullYear();

            // Load announcements on page load
            loadAnnouncements();

            // Ensure date input cannot select past dates (sets datepicker min)
            const dateInput = document.getElementById('date');
            if (dateInput) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                dateInput.min = `${yyyy}-${mm}-${dd}`;
            }

            const form = document.getElementById('new-announcement-form');
            form.onsubmit = function(e) {
                e.preventDefault();

                // --- ADDED: Prevent posting announcements with a past date ---
                const dateVal = document.getElementById('date').value;
                if (dateVal) {
                    // normalize both dates to local start-of-day to avoid timezone issues
                    const selected = new Date(dateVal);
                    selected.setHours(0,0,0,0);
                    const todayStart = new Date();
                    todayStart.setHours(0,0,0,0);

                    if (selected < todayStart) {
                        alert('The display date cannot be in the past. Please choose today or a future date.');
                        document.getElementById('date').focus();
                        return; // stop submission
                    }
                }
                const data = {
                    title: document.getElementById('title').value,
                    content: document.getElementById('content').value,
                    tag: document.getElementById('tag').value,
                    visibility: document.getElementById('visibility').value,
                    date: document.getElementById('date').value
                };

                fetch('../api/announcements.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                })
                .then(res => res.text())
                .then(txt => {
                    if (isHtmlLoginResponse(txt)) {
                        alert('Session expired or server returned a login page. Redirecting to login.');
                        window.location.href = 'teacher-login.php';
                        return;
                    }
                    try {
                        const result = JSON.parse(txt);
                        if (result.success) {
                            alert('Announcement posted successfully!');
                            hideModal();
                            form.reset();
                            loadAnnouncements();
                        } else {
                            alert('Error: ' + (result.message || 'Unknown error'));
                        }
                    } catch (e) {
                        console.error('Fetch error (non-JSON):', txt);
                        alert('Unexpected server response when posting announcement. Please check your session or server logs.');
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    alert('Error posting announcement: ' + err.message);
                });
            };

            // ===== Filters =====
            const toggleBtn = document.getElementById('filter-toggle');
            const panel = document.getElementById('filter-panel');
            const applyBtn = document.getElementById('filter-apply');
            const resetBtn = document.getElementById('filter-reset');
            const textInput = document.getElementById('filter-text');
            const tagSelect = document.getElementById('filter-tag');
            const fromInput = document.getElementById('filter-from');
            const toInput = document.getElementById('filter-to');

            function normalize(str){ return (str || '').toString().toLowerCase(); }

            function applyFilters(){
                const query = normalize(textInput.value);
                const tag = normalize(tagSelect.value);
                const fromVal = fromInput.value ? new Date(fromInput.value) : null;
                const toVal = toInput.value ? new Date(toInput.value) : null;

                const items = document.querySelectorAll('#announcement-list .announcement-item');
                let visibleCount = 0;
                
                items.forEach(li => {
                    let visible = true;
                    
                    if (query) {
                        const titleEl = li.querySelector('strong');
                        const titleText = titleEl ? normalize(titleEl.textContent) : '';
                        if (!titleText.includes(query)) visible = false;
                    }
                    
                    if (visible && tag) {
                        const badgeEl = li.querySelector('span[style*="background"]');
                        const badgeText = badgeEl ? normalize(badgeEl.textContent) : '';
                        
                        let shouldMatch = false;
                        if (tag === 'all') {
                            shouldMatch = true;
                        } else if (tag === 'important' && badgeText.includes('important')) {
                            shouldMatch = true;
                        } else if (tag === 'new' && badgeText.includes('new')) {
                            shouldMatch = true;
                        } else if (tag === 'info' && badgeText.includes('info')) {
                            shouldMatch = true;
                        } else if (tag === 'students' && badgeText.includes('students only')) {
                            shouldMatch = true;
                        } else if (tag === 'teachers' && badgeText.includes('teachers only')) {
                            shouldMatch = true;
                        } else if (tag === 'both' && badgeText.includes('both')) {
                            shouldMatch = true;
                        }
                        
                        if (!shouldMatch) visible = false;
                    }
                    
                    if (visible && (fromVal || toVal)) {
                        const dateText = li.querySelector('div[style*="color:#666"]')?.textContent.trim();
                        if (dateText) {
                            const d = new Date(dateText);
                            if (!isNaN(d)) {
                                if (fromVal && d < fromVal) visible = false;
                                if (toVal) {
                                    const toEnd = new Date(toVal);
                                    toEnd.setHours(23,59,59,999);
                                    if (d > toEnd) visible = false;
                                }
                            }
                        } else {
                            visible = false;
                        }
                    }
                    
                    li.style.display = visible ? '' : 'none';
                    if (visible) visibleCount++;
                });
                
                // Show "no results" message if all items are hidden
                if (visibleCount === 0 && items.length > 0) {
                    let existingEmpty = document.querySelector('.empty-filter-msg');
                    if (!existingEmpty) {
                        const emptyMsg = document.createElement('li');
                        emptyMsg.className = 'loading-message empty-filter-msg';
                        emptyMsg.style.cssText = 'padding:12px;text-align:center;color:#999;';
                        emptyMsg.textContent = 'No announcements match your filters.';
                        document.getElementById('announcement-list').appendChild(emptyMsg);
                    }
                }
            }

            toggleBtn?.addEventListener('click', () => {
                const isOpen = panel.classList.toggle('open');
                if (isOpen) {
                    panel.removeAttribute('hidden');
                    textInput?.focus();
                } else {
                    panel.setAttribute('hidden','');
                }
            });
            
            applyBtn?.addEventListener('click', () => {
                const emptyMsg = document.querySelector('.empty-filter-msg');
                if (emptyMsg) emptyMsg.remove();
                applyFilters();
            });
            
            resetBtn?.addEventListener('click', () => {
                textInput.value = '';
                tagSelect.value = '';
                fromInput.value = '';
                toInput.value = '';
                const emptyMsg = document.querySelector('.empty-filter-msg');
                if (emptyMsg) emptyMsg.remove();
                applyFilters();
            });
            
            textInput?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { 
                    e.preventDefault();
                    const emptyMsg = document.querySelector('.empty-filter-msg');
                    if (emptyMsg) emptyMsg.remove();
                    applyFilters(); 
                }
            });
            
            // Real-time search as user types
            textInput?.addEventListener('input', () => {
                const emptyMsg = document.querySelector('.empty-filter-msg');
                if (emptyMsg) emptyMsg.remove();
                applyFilters();
            });
            
            // Apply filters when tag select changes
            tagSelect?.addEventListener('change', () => {
                const emptyMsg = document.querySelector('.empty-filter-msg');
                if (emptyMsg) emptyMsg.remove();
                applyFilters();
            });
        });
    </script>
</body>
</html>
