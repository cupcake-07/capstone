<?php
// filepath: c:\xampp\htdocs\capstone\teachers\teacher-announcements.php
session_start();

// Optional: Check if user is logged in (modify based on your auth system)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: ../login.php');
//     exit;
// }

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>

    <link rel="stylesheet" href="announcement.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
        
</head>
<body>
    <!--Top Navar-->
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="navbar-logo">GGF</div>
            <div class="navbar-text">
                <div class="navbar-title">Glorious God's Family</div>
                <div class="navbar-subtitle">Christian School</div>
            </div>
        </div>
        <div class="navbar-actions">
            <div class="user-menu">
                <span><?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="logout.php">
                    <img src="loginswitch.png" id="loginswitch"></img>
                </a>
            </div>
        </div>
    </nav>

    <!--Main Page Container-->
    <div class="page-wrapper">
        <!--Side Bar-->
        <aside class="side">
            <nav class="nav">
                <a href="teacher.php">Dashboard</a>
                <a href="tprofile.php">Profile</a>
                <a href="student_schedule.php">Schedule</a>        
                <a href="attendance.php">Attendance</a>
                <a href="listofstudents.php">Lists of students</a>
                <a href="grades.php">Grades</a>
                <a href="school_calendar.php">School Calendar</a>
                <a href="teacher-announcements.php" class="active">Announcements</a>
                <a href="teacherslist.php">Teachers</a>
                <a href="settings.php">Settings</a>
            </nav>
            <div class="side-foot">Logged in as <strong><?php echo htmlspecialchars($teacher_name); ?></strong></div>
        </aside>
        
        <!--Main Content-->
        <main class="main">
            <header class="header">
                <h1>School Announcements</h1>
            </header>

            <!--Announcement content-->
            <section class="announcement-container">
                <h2 class="page-title">Latest Updates (Real-Time)</h2>

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
                    <div class="form-actions"></div>
                        <button type="button" onclick="hideModal()">Cancel</button>
                        <button type="submit" id="submit-btn">Post Announcement</button>
                    </div>
                </form>
            </div>
        </div>

        <!--Footer-->
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p>123 Faith Avenue</p>
                    <p>Your City, ST 12345</p>
                    <p>Phone: (555) 123-4567</p>
                    <p>Email: info@gloriousgod.edu</p>
                </div>
                <div class="footer-section">
                    <h3>Connect With Us</h3>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook">
                            <svg xlmns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-facebook">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-instagram">
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.5" y1="6.5" y2="6.5"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Twitter">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-twitter">
                                <path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2 1.7-1.4 1.2-4-1.2-5.4l-.4-.4a7.9 7.9 0 0 0-1.7-1.1c1.5-1.4 3.7-2 6.5-1.6 3-1.6 5.5-2.8 7.3-3.6 1.8.8 2.6 2.2 2.6 3.6z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>System Info</h3>
                    <p>Schoolwide Management System</p>
                    <p>Version 1.0.0</p>
                </div>
            </div>
            <div class="footer-bottom">
                    <p>&copy; 2025 Glorious God Family Christian School. All rights reserved.</p>
                    <div class="footer-links">
                        <a href="privacy.html">Privacy Policy</a> |
                        <a href="terms.html">Terms of Service</a>
                    </div>
                <div class="copyright">© <span id="year">2025</span> Schoolwide Management System</div>
            </div>
        </footer>
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

        // Load announcements from API
        function loadAnnouncements() {
            fetch('../api/announcements.php?action=list')
                .then(res => res.json())
                .then(data => {
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

        // Delete announcement function
        function deleteAnnouncement(id) {
            fetch('../api/announcements.php?action=delete', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id) })
            })
            .then(res => res.json())
            .then(result => {
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

        // Form submission handling
        document.addEventListener('DOMContentLoaded', function() {
            // Load announcements on page load
            loadAnnouncements();

            const form = document.getElementById('new-announcement-form');
            form.onsubmit = function(e) {
                e.preventDefault();
                
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
                    body: JSON.stringify(data)
                })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(result => {
                    if (result.success) {
                        alert('Announcement posted successfully!');
                        hideModal();
                        form.reset();
                        // Reload announcements instead of full page reload
                        loadAnnouncements();
                    } else {
                        alert('Error: ' + (result.message || 'Unknown error'));
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

            function parseItemDate(li){
                // Prefer data-date attribute if available
                const dataAttr = li.getAttribute('data-date');
                if (dataAttr) {
                    const d = new Date(dataAttr);
                    if (!isNaN(d)) return d;
                }
                // Fallback to visible date text
                const dateEl = li.querySelector('.announcement-date, .item-meta time, .item-meta .date');
                if (dateEl) {
                    const d = new Date(dateEl.textContent.trim());
                    if (!isNaN(d)) return d;
                }
                return null;
            }

            function getItemTag(li){
                const tagEl = li.querySelector('.announcement-tag, .item-tag');
                if (!tagEl) return '';
                return normalize(tagEl.textContent);
            }

            function getItemText(li){
                const titleEl = li.querySelector('.item-title, .announcement-title, strong');
                const contentEl = li.querySelector('.item-content, .announcement-content');
                const titleText = titleEl ? titleEl.textContent : '';
                const contentText = contentEl ? contentEl.textContent : '';
                return normalize(titleText + ' ' + contentText);
            }

            function applyFilters(){
                const query = normalize(textInput.value);
                const tag = normalize(tagSelect.value);
                const fromVal = fromInput.value ? new Date(fromInput.value) : null;
                const toVal = toInput.value ? new Date(toInput.value) : null;

                const items = document.querySelectorAll('#announcement-list .announcement-item');
                let visibleCount = 0;
                
                items.forEach(li => {
                    let visible = true;
                    
                    // Text filter - search in title and all text content
                    if (query) {
                        const titleEl = li.querySelector('strong');
                        const titleText = titleEl ? normalize(titleEl.textContent) : '';
                        if (!titleText.includes(query)) visible = false;
                    }
                    
                    // Tag/Visibility filter
                    if (visible && tag) {
                        const badgeEl = li.querySelector('span[style*="background"]');
                        const badgeText = badgeEl ? normalize(badgeEl.textContent) : '';
                        
                        // Map tag values to badge text
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
                    
                    // Date filter
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
                // Remove empty message before applying filters
                const emptyMsg = document.querySelector('.empty-filter-msg');
                if (emptyMsg) emptyMsg.remove();
                applyFilters();
            });
            
            resetBtn?.addEventListener('click', () => {
                textInput.value = '';
                tagSelect.value = '';
                fromInput.value = '';
                toInput.value = '';
                // Remove empty message
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