<?php
// Use a separate session name for teachers
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

// Handle AJAX requests for adding/deleting events
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // start output buffering so we can clear accidental output (warnings/html) before sending JSON
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    @ob_start();

    // helper to send clean JSON and exit
    $send_json = function($payload) {
        if (ob_get_length() > 0) @ob_clean();
        echo json_encode($payload);
        // ensure nothing else is sent
        if (ob_get_level() > 0) @ob_end_flush();
        exit;
    };

    $action = $_POST['action'] ?? '';

    if ($action === 'add_event') {
        $eventDate = $_POST['event_date'] ?? '';
        $eventTitle = $_POST['event_title'] ?? '';
        
        if ($eventDate && $eventTitle) {
            // normalize date to YYYY-MM-DD (strip time) and validate not in the past
            $normDate = date('Y-m-d', strtotime($eventDate));
            if ($normDate === false || $normDate === '1970-01-01') {
                $send_json(['success' => false, 'message' => 'Invalid event date.']);
            }
            $today = date('Y-m-d');
            if ($normDate < $today) {
                $send_json(['success' => false, 'message' => 'Cannot add an event in the past.']);
            }

            // Use normalized date for duplicate check and insertion
            $checkStmt = $conn->prepare("SELECT id FROM school_events WHERE DATE(event_date) = ? AND title = ?");
            if ($checkStmt) {
                $checkStmt->bind_param('ss', $normDate, $eventTitle);
                $checkStmt->execute();
                if (method_exists($checkStmt, 'get_result')) {
                    $result = $checkStmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $checkStmt->close();
                        $send_json(['success' => false, 'message' => 'Event with this title already exists on this day.']);
                    }
                } else {
                    $checkStmt->store_result();
                    if ($checkStmt->num_rows > 0) {
                        $checkStmt->close();
                        $send_json(['success' => false, 'message' => 'Event with this title already exists on this day.']);
                    }
                }
                $checkStmt->close();
            } else {
                $send_json(['success' => false, 'message' => 'Database error (prepare check).']);
            }

            // Insert event using normalized date (store as date string)
            $insertStmt = $conn->prepare("INSERT INTO school_events (event_date, title) VALUES (?, ?)");
            if ($insertStmt) {
                $insertStmt->bind_param('ss', $normDate, $eventTitle);
                if ($insertStmt->execute()) {
                    $newEventId = $conn->insert_id;

                    // --- CHANGED: create announcement using only columns that exist ---
                    $tblRes = $conn->query("SHOW TABLES LIKE 'announcements'");
                    if ($tblRes && $tblRes->num_rows > 0) {
                        // get actual columns
                        $colsRes = $conn->query("SHOW COLUMNS FROM announcements");
                        $existingCols = [];
                        if ($colsRes) {
                            while ($c = $colsRes->fetch_assoc()) $existingCols[] = $c['Field'];
                        }

                        // candidate values (prefer sensible names)
                        $annTitle = $eventTitle;
                        $annContent = "School event: {$eventTitle} on {$eventDate}";
                        $annPubDate = $eventDate;
                        $annVisibility = 'students';
                        $annType = 'event';
                        $annEventId = $newEventId;

                        // mapping of preferred column => value
                        $map = [
                            'title' => $annTitle,
                            'content' => $annContent,
                            'pub_date' => $annPubDate,
                            'date' => $annPubDate,
                            'published_at' => $annPubDate,
                            'visibility' => $annVisibility,
                            'type' => $annType,
                            'event_id' => $annEventId,
                        ];

                        $colsToInsert = [];
                        $params = [];
                        $types = '';
                        foreach ($map as $col => $val) {
                            if (in_array($col, $existingCols, true)) {
                                $colsToInsert[] = $col;
                                // event_id should be integer if present
                                if ($col === 'event_id') {
                                    $params[] = (int)$val;
                                    $types .= 'i';
                                } else {
                                    $params[] = (string)$val;
                                    $types .= 's';
                                }
                            }
                        }

                        if (count($colsToInsert) > 0) {
                            $placeholders = implode(',', array_fill(0, count($colsToInsert), '?'));
                            $colList = '`' . implode('`,`', $colsToInsert) . '`';
                            $sql = "INSERT INTO announcements ($colList) VALUES ($placeholders)";
                            $annStmt = $conn->prepare($sql);
                            if ($annStmt) {
                                // bind params by reference
                                $refs = [];
                                $refs[] = & $types;
                                for ($i = 0; $i < count($params); $i++) {
                                    $refs[] = & $params[$i];
                                }
                                @ call_user_func_array([$annStmt, 'bind_param'], $refs);
                                @ $annStmt->execute();
                                $annStmt->close();
                            }
                        }
                    }
                    // --- end changed block ---

                    $insertStmt->close();
                    $send_json(['success' => true, 'message' => 'Event added successfully!', 'id' => $newEventId]);
                } else {
                    $err = $conn->error ?: 'unknown';
                    $insertStmt->close();
                    $send_json(['success' => false, 'message' => 'Error adding event. DB error: ' . substr($err,0,200)]);
                }
            } else {
                $send_json(['success' => false, 'message' => 'Database error (prepare insert).']);
            }
        }
        $send_json(['success' => false, 'message' => 'Please enter both date and event title.']);
    }

    if ($action === 'delete_event') {
        $eventId = intval($_POST['event_id'] ?? 0);

        if ($eventId > 0) {
            // Fetch event details first
            $selStmt = $conn->prepare("SELECT event_date, title FROM school_events WHERE id = ? LIMIT 1");
            $eventDate = null;
            $eventTitle = null;
            if ($selStmt) {
                $selStmt->bind_param('i', $eventId);
                $selStmt->execute();
                if (method_exists($selStmt, 'get_result')) {
                    $res = $selStmt->get_result();
                    if ($res && $res->num_rows === 1) {
                        $row = $res->fetch_assoc();
                        $eventDate = $row['event_date'];
                        $eventTitle = $row['title'];
                    }
                } else {
                    $selStmt->store_result();
                    if ($selStmt->num_rows === 1) {
                        $selStmt->bind_result($edate, $etitle);
                        $selStmt->fetch();
                        $eventDate = $edate;
                        $eventTitle = $etitle;
                    }
                }
                $selStmt->close();
            }

            $deleteStmt = $conn->prepare("DELETE FROM school_events WHERE id = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param('i', $eventId);
                if ($deleteStmt->execute()) {
                    // --- CHANGED: delete matching announcement(s) using available columns ---
                    if ($eventTitle !== null && $eventDate !== null) {
                        $tblRes = $conn->query("SHOW TABLES LIKE 'announcements'");
                        if ($tblRes && $tblRes->num_rows > 0) {
                            // determine available columns
                            $colsRes = $conn->query("SHOW COLUMNS FROM announcements");
                            $existingCols = [];
                            if ($colsRes) {
                                while ($c = $colsRes->fetch_assoc()) $existingCols[] = $c['Field'];
                            }

                            // prefer deleting by event_id if exists, otherwise try title + date + type where possible
                            if (in_array('event_id', $existingCols, true)) {
                                $delStmt = $conn->prepare("DELETE FROM announcements WHERE event_id = ? LIMIT 1");
                                if ($delStmt) {
                                    $idVal = (int)$eventId;
                                    $delStmt->bind_param('i', $idVal);
                                    @ $delStmt->execute();
                                    $delStmt->close();
                                }
                            } else {
                                // build dynamic where parts
                                $where = [];
                                $params = [];
                                $types = '';
                                if (in_array('title', $existingCols, true)) {
                                    $where[] = 'title = ?';
                                    $params[] = (string)$eventTitle;
                                    $types .= 's';
                                }
                                // prefer 'pub_date' then 'date' then 'published_at'
                                if (in_array('pub_date', $existingCols, true)) {
                                    $where[] = 'pub_date = ?';
                                    $params[] = (string)$eventDate;
                                    $types .= 's';
                                } elseif (in_array('date', $existingCols, true)) {
                                    $where[] = 'date = ?';
                                    $params[] = (string)$eventDate;
                                    $types .= 's';
                                } elseif (in_array('published_at', $existingCols, true)) {
                                    $where[] = 'published_at = ?';
                                    $params[] = (string)$eventDate;
                                    $types .= 's';
                                }

                                if (in_array('type', $existingCols, true)) {
                                    $where[] = "type = ?";
                                    $params[] = 'event';
                                    $types .= 's';
                                }

                                if (count($where) > 0) {
                                    $sql = "DELETE FROM announcements WHERE " . implode(' AND ', $where) . " LIMIT 1";
                                    $delStmt = $conn->prepare($sql);
                                    if ($delStmt) {
                                        $refs = [];
                                        $refs[] = & $types;
                                        for ($i = 0; $i < count($params); $i++) {
                                            $refs[] = & $params[$i];
                                        }
                                        @ call_user_func_array([$delStmt, 'bind_param'], $refs);
                                        @ $delStmt->execute();
                                        $delStmt->close();
                                    }
                                }
                            }
                        }
                    }
                    // --- end changed block ---

                    $deleteStmt->close();
                    $send_json(['success' => true, 'message' => 'Event deleted.']);
                } else {
                    $deleteStmt->close();
                    $send_json(['success' => false, 'message' => 'Error deleting event.']);
                }
            } else {
                $send_json(['success' => false, 'message' => 'Database error (prepare delete).']);
            }
        }
        $send_json(['success' => false, 'message' => 'Invalid event id.']);
    }

    // fallback
    $send_json(['success' => false, 'message' => 'Unknown action.']);
}

// Fetch all events from database
$eventsData = [];
$eventsStmt = $conn->query("SELECT id, event_date, title FROM school_events ORDER BY event_date ASC");
if ($eventsStmt) {
    while ($row = $eventsStmt->fetch_assoc()) {
        // normalize to YYYY-MM-DD to avoid mismatches (strip time portion)
        $rawDate = $row['event_date'];
        $date = date('Y-m-d', strtotime($rawDate));
        if (!$date) continue;
        if (!isset($eventsData[$date])) {
            $eventsData[$date] = [];
        }
        $eventsData[$date][] = ['id' => (int)$row['id'], 'title' => $row['title']];
    }
}

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>School Calendar Elegant View</title>
  <link rel="stylesheet" href="teacher.css" />
  <link rel="stylesheet" href="calendar.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <!-- TOP NAVBAR -->
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
          <button type="button" style="background: none; border: none; padding: 8px 16px; color: #fff; cursor: pointer; font-size: 14px; border-radius: 4px; background-color: #dc3545; transition: background-color 0.3s ease;">
            Logout
          </button>
        </a>
      </div>
    </div>
  </nav>

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <!-- SIDEBAR -->
    <aside class="side">
      <nav class="nav">
        <a href="teacher.php">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>
        
        <a href="listofstudents.php">Lists of students</a>
        <a href="grades.php">Grades</a>
        <a href="school_calendar.php" class="active">School Calendar</a>
        <a href="teacher-announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="teacher-settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>School Calendar</h1>
      </header>
  
      <section class="calendar-container">
        <div class="calendar-header">
          <button id="prev-month" class="nav-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left">
              <path d="m15 18-6-6 6-6"/></svg>
          </button>
          <h2 id="current-month-year">Month Year</h2>
          <button id="next-month" class="nav-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right">
              <path d="m9 18 6-6-6-6"/></svg>
          </button>
        </div>

        <div class="calendar-grid">
          <div class="day-name">Sun</div>
          <div class="day-name">Mon</div>
          <div class="day-name">Tue</div>
          <div class="day-name">Wed</div>
          <div class="day-name">Thu</div>
          <div class="day-name">Fri</div>
          <div class="day-name">Sat</div>
        </div>

        <div class="calendar-content-wrapper">
          <div class="events-management-card card">
            <div class="card-title">📅 Add New School Event</div>
            <form id="add-event-form" class="event-form">
              <div class="form-group">
                <input type="date" id="event-date" required>
              </div>
              <div class="form-group">
                <input type="text" id="event-title" placeholder="Event Title" required>
              </div>
              <button type="submit" class="add-event-btn">
                <span class="btn-icon">➕</span>
                <span class="btn-text">Add Event</span>
              </button>
            </form>
            <div id="message-box"></div>
          </div>

          <div class="events-list-card card">
            <div class="card-title">School Events for 
              <span id="select-date-display">Today</span>
            </div>
            <ul id="events-lists">
              <li>Loading events...</li>
            </ul>
          </div>
        </div>
      </section>

    
    </main>

    <footer class="footer">© <span id="year">2025</span> Schoolwide Management System</footer>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // === DOM Elements ===
      const monthYearDisplay = document.getElementById('current-month-year');
      const calendarGrid = document.querySelector('.calendar-grid');
      const prevMonthButton = document.getElementById('prev-month');
      const nextMonthButton = document.getElementById('next-month');
      const addEventForm = document.getElementById('add-event-form');
      const eventDateInput = document.getElementById('event-date');
      const eventTitleInput = document.getElementById('event-title');
      const eventsList = document.getElementById('events-lists');
      const messageBox = document.getElementById('message-box');
      const selectDateDisplay = document.getElementById('select-date-display');
      const today = new Date();

      // === State Management ===
      let currentDate = new Date();
      let selectedDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      
      // Load events from PHP (raw)
      let schoolEvents = <?php echo json_encode($eventsData); ?>;

      // Normalize client-side keys defensively: ensure YYYY-MM-DD keys and only arrays with items remain
      (function normalizeSchoolEvents() {
        const raw = schoolEvents || {};
        const normalized = {};
        Object.keys(raw).forEach(k => {
          // extract YYYY-MM-DD if present, else use original key
          const m = k.match(/\d{4}-\d{2}-\d{2}/);
          const key = m ? m[0] : k;
          const val = raw[k];
          if (Array.isArray(val) && val.length > 0) {
            normalized[key] = val;
          }
        });
        schoolEvents = normalized;
      })();

      // ...existing helper functions...

      const formatDateKey = (date) => {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      };

      const formatDisplayDate = (date) => {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
      };

      const renderCalendar = () => {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        monthYearDisplay.textContent = currentDate.toLocaleDateString('en-US', {
          month: 'long',
          year: 'numeric'
        });

        while (calendarGrid.children.length > 7) {
          calendarGrid.removeChild(calendarGrid.lastChild);
        }

        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDayOfMonth; i++) {
          const emptyDay = document.createElement('div');
          emptyDay.classList.add('calendar-day', 'empty');
          calendarGrid.appendChild(emptyDay);
        }

        for (let day = 1; day <= daysInMonth; day++) {
          const dayDate = new Date(year, month, day);
          const dateKey = formatDateKey(dayDate);

          const dayDiv = document.createElement('div');
          dayDiv.classList.add('calendar-day');
          dayDiv.innerHTML = `<span>${day}</span>`;
          dayDiv.dataset.date = dateKey;
          
          const isToday = today.toDateString() === dayDate.toDateString();
          if (isToday) {
            dayDiv.classList.add('today');
          }

          const isSelected = selectedDate && selectedDate.toDateString() === dayDate.toDateString();
          if (isSelected) {
            dayDiv.classList.add('selected');
          }

          if (Array.isArray(schoolEvents[dateKey]) && schoolEvents[dateKey].length > 0) {
            dayDiv.classList.add('has-event');
            const marker = document.createElement('div');
            marker.classList.add('event-marker');
            dayDiv.appendChild(marker);
          }

          dayDiv.addEventListener('click', () => handleDayClick(dayDiv, dayDate));
          calendarGrid.appendChild(dayDiv);
        }
        
        updateEventsList(selectedDate);
      };

      const handleDayClick = (dayElement, dateObj) => {
        document.querySelectorAll('.calendar-day.selected').forEach(d => {
          d.classList.remove('selected');
        });

        dayElement.classList.add('selected');
        selectedDate = dateObj;
        updateEventsList(selectedDate);
      };

      const updateEventsList = (dateObj) => {
        const dateKey = formatDateKey(dateObj);
        const events = schoolEvents[dateKey] || [];
        
        selectDateDisplay.textContent = formatDisplayDate(dateObj);
        eventDateInput.value = dateKey;

        eventsList.innerHTML = '';

        if (events.length === 0) {
          const li = document.createElement('li');
          li.textContent = 'No school events scheduled for this day.';
          li.className = 'text-gray-500 italic';
          eventsList.appendChild(li);
        } else {
          events.forEach(event => {
            const li = document.createElement('li');
            li.innerHTML = `
              <strong>${event.title}</strong> 
              <button class="delete-event" data-event-id="${event.id}" data-date="${dateKey}" title="Delete Event">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500 hover:text-red-700">
                  <path d="M3 6h18"/>
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                  <line x1="10" x2="10" y1="11" y2="17"/>
                  <line x1="14" x2="14" y1="11" y2="17"/>
                </svg>
              </button>`;
            eventsList.appendChild(li);
          });
        }
      };

      const showMessage = (message, type) => {
        messageBox.textContent = message;
        messageBox.className = `message-box message-${type}`;
        messageBox.style.display = 'block';

        setTimeout(() => {
          messageBox.style.display = 'none';
        }, 4000);
      };

      // ...existing event handlers...

      prevMonthButton.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
      });

      nextMonthButton.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
      });

      // set min date for event-date input to today (prevents picking past dates)
      const dateInput = document.getElementById('event-date');
      if (dateInput) {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const minDate = `${yyyy}-${mm}-${dd}`;
        dateInput.setAttribute('min', minDate);
        // if current value is before min, set it to min
        if (dateInput.value && dateInput.value < minDate) dateInput.value = minDate;
      }

      addEventForm.addEventListener('submit', (e) => {
        e.preventDefault();

        const dateKey = eventDateInput.value;
        const title = eventTitleInput.value.trim();

        // client-side past-date check
        const todayStr = (new Date()).toISOString().slice(0,10);
        if (!dateKey || dateKey < todayStr) {
          showMessage('Cannot add an event in the past. Please pick today or a future date.', 'error');
          return;
        }

        if (dateKey && title) {
          const formData = new FormData();
          formData.append('action', 'add_event');
          formData.append('event_date', dateKey);
          formData.append('event_title', title);

          fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          })
          .then(res => res.text())
          .then(text => {
            let data;
            try {
              data = JSON.parse(text);
            } catch (err) {
              console.error('Invalid JSON response from server (add):', text);
              showMessage('Server error: invalid response. Check server logs.', 'error');
              return;
            }
            if (data.success) {
              showMessage(data.message, 'success');
              eventTitleInput.value = '';

              if (!schoolEvents[dateKey]) schoolEvents[dateKey] = [];
              const newId = data.id || Date.now();
              schoolEvents[dateKey].push({ id: newId, title: title });
              renderCalendar();
            } else {
              showMessage(data.message || 'Error adding event.', 'error');
            }
          })
          .catch(err => {
            console.error('Fetch error (add):', err);
            showMessage('Error adding event.', 'error');
          });
        } else {
          showMessage('Please enter both date and event title.', 'error');
        }
      });
      
      eventsList.addEventListener('click', (e) => {
        const deleteButton = e.target.closest('.delete-event');
        if (deleteButton) {
          const eventId = deleteButton.dataset.eventId;
          const dateKey = deleteButton.dataset.date;

          const formData = new FormData();
          formData.append('action', 'delete_event');
          formData.append('event_id', eventId);

          fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          })
          .then(res => res.text())
          .then(text => {
            let data;
            try {
              data = JSON.parse(text);
            } catch (err) {
              console.error('Invalid JSON response from server (delete):', text);
              showMessage('Server error: invalid response. Check server logs.', 'error');
              return;
            }
            if (data.success) {
              showMessage(data.message, 'success');

              if (schoolEvents[dateKey]) {
                schoolEvents[dateKey] = schoolEvents[dateKey].filter(e => e.id != eventId);
                if (schoolEvents[dateKey].length === 0) delete schoolEvents[dateKey];
              }

              renderCalendar();
            } else {
              showMessage(data.message || 'Error deleting event.', 'error');
            }
          })
          .catch(err => {
            console.error('Fetch error (delete):', err);
            showMessage('Error deleting event.', 'error');
          });
        }
      });

      // Initialize
      renderCalendar();
    });

    // Update year in footer
    const yearSpan = document.getElementById('year');
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();
  </script>
</body>
</html>
