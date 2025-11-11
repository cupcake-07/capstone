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
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_event') {
        $eventDate = $_POST['event_date'] ?? '';
        $eventTitle = $_POST['event_title'] ?? '';
        
        if ($eventDate && $eventTitle) {
            // Check for duplicates
            $checkStmt = $conn->prepare("SELECT id FROM school_events WHERE event_date = ? AND title = ?");
            if ($checkStmt) {
                $checkStmt->bind_param('ss', $eventDate, $eventTitle);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Event with this title already exists on this day.']);
                    $checkStmt->close();
                    exit;
                }
                $checkStmt->close();
            }
            
            // Insert event
            $insertStmt = $conn->prepare("INSERT INTO school_events (event_date, title) VALUES (?, ?)");
            if ($insertStmt) {
                $insertStmt->bind_param('ss', $eventDate, $eventTitle);
                if ($insertStmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Event added successfully!']);
                    $insertStmt->close();
                    exit;
                }
                $insertStmt->close();
            }
            echo json_encode(['success' => false, 'message' => 'Error adding event.']);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Please enter both date and event title.']);
        exit;
    }
    
    if ($action === 'delete_event') {
        $eventId = intval($_POST['event_id'] ?? 0);
        
        if ($eventId > 0) {
            $deleteStmt = $conn->prepare("DELETE FROM school_events WHERE id = ?");
            if ($deleteStmt) {
                $deleteStmt->bind_param('i', $eventId);
                if ($deleteStmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Event deleted.']);
                    $deleteStmt->close();
                    exit;
                }
                $deleteStmt->close();
            }
        }
        echo json_encode(['success' => false, 'message' => 'Error deleting event.']);
        exit;
    }
}

// Fetch all events from database
$eventsData = [];
$eventsStmt = $conn->query("SELECT id, event_date, title FROM school_events ORDER BY event_date ASC");
if ($eventsStmt) {
    while ($row = $eventsStmt->fetch_assoc()) {
        $date = $row['event_date'];
        if (!isset($eventsData[$date])) {
            $eventsData[$date] = [];
        }
        $eventsData[$date][] = ['id' => $row['id'], 'title' => $row['title']];
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
      <div class="navbar-logo">GGF</div>
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
      <div class="user-menu">
        <span><?php echo $user_name; ?></span>
        <a href="../logout.php">
          <img src="loginswitch.png" id="loginswitch" alt="login switch"/>
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
        <a href="attendance.php">Attendance</a>
        <a href="listofstudents.php">Lists of students</a>
        <a href="grades.php">Grades</a>
        <a href="school_calendar.php" class="active">School Calendar</a>
        <a href="announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="settings.php">Settings</a>
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

        <div class="events-management-card card">
          <div class="card-title" style="color: var(--green);">Add New School Event</div>
          <form id="add-event-form">
            <input type="date" id="event-date" required>
            <input type="text" id="event-title" placeholder="Event Title" required>
            <button type="submit">Add Event</button>
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
      
      // Load events from PHP
      let schoolEvents = <?php echo json_encode($eventsData); ?>;

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

          if (schoolEvents[dateKey] && schoolEvents[dateKey].length > 0) {
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

      addEventForm.addEventListener('submit', (e) => {
        e.preventDefault();

        const dateKey = eventDateInput.value;
        const title = eventTitleInput.value.trim();

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
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              showMessage(data.message, 'success');
              eventTitleInput.value = '';
              
              if (!schoolEvents[dateKey]) {
                schoolEvents[dateKey] = [];
              }
              schoolEvents[dateKey].push({ id: Date.now(), title: title });
              
              renderCalendar();
            } else {
              showMessage(data.message, 'error');
            }
          })
          .catch(err => {
            console.error('Error:', err);
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
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              showMessage(data.message, 'success');
              
              if (schoolEvents[dateKey]) {
                schoolEvents[dateKey] = schoolEvents[dateKey].filter(e => e.id != eventId);
                if (schoolEvents[dateKey].length === 0) {
                  delete schoolEvents[dateKey];
                }
              }
              
              renderCalendar();
            } else {
              showMessage(data.message, 'error');
            }
          })
          .catch(err => {
            console.error('Error:', err);
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
