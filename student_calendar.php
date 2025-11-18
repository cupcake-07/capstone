<?php
// Use student session name
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/config/database.php';

// Redirect if not logged in as student
if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'student') {
    header('Location: student-login.php');
    exit;
}

// Remove server-side AJAX add/delete handling for students - deny POSTs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// Fetch events
$eventsData = [];
$eventsStmt = $conn->query("SELECT id, event_date, title FROM school_events ORDER BY event_date ASC");
if ($eventsStmt) {
    while ($row = $eventsStmt->fetch_assoc()) {
        $rawDate = $row['event_date'];
        $date = date('Y-m-d', strtotime($rawDate));
        if (!$date) continue;
        if (!isset($eventsData[$date])) $eventsData[$date] = [];
        $eventsData[$date][] = ['id' => (int)$row['id'], 'title' => $row['title']];
    }
}

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Student');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>School Calendar</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <link rel="stylesheet" href="css/student_calendar.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <!-- Top NAV -->
  <nav class="navbar">
    <div class="navbar-brand">
     
        <img src="g2flogo.png" alt="Glorious God's Family Logo" style="height: 40px; margin-left:-20px"  />
     
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
      <div class="user-menu">
        <span><?php echo $user_name; ?></span>
        <a href="logout.php" class="logout-btn" title="Logout">
         
        </a>
      </div>
    </div>
  </nav>

  <div class="page-wrapper">
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>

    <main class="main">
      <header class="header">
        <h1 style="color:#fd4ba7">School Calendar</h1>
      </header> 

      <section class="calendar-container">
        <div class="calendar-header">
          <button id="prev-month" class="nav-button">◀</button>
          <h2 id="current-month-year">Month Year</h2>
          <button id="next-month" class="nav-button">▶</button>
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
          <div class="events-list-card card">
            <div class="card-title">
              School Events for <span id="select-date-display">Today</span>
            </div>
            <ul id="events-lists">
              <li>Loading events...</li>
            </ul>
          </div>
        </div>
      </section>
    </main>
  </div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const monthYearDisplay = document.getElementById('current-month-year');
    const calendarGrid = document.querySelector('.calendar-grid');
    const prevMonthButton = document.getElementById('prev-month');
    const nextMonthButton = document.getElementById('next-month');
    const eventsList = document.getElementById('events-lists');
    const messageBox = document.getElementById('message-box');
    const selectDateDisplay = document.getElementById('select-date-display');
    const today = new Date();

    let currentDate = new Date();
    let selectedDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    let schoolEvents = <?php echo json_encode($eventsData); ?>;

    (function normalizeSchoolEvents() {
      const raw = schoolEvents || {};
      const normalized = {};
      Object.keys(raw).forEach(k => {
        const m = k.match(/\d{4}-\d{2}-\d{2}/);
        const key = m ? m[0] : k;
        const val = raw[k];
        if (Array.isArray(val) && val.length > 0) {
          normalized[key] = val;
        }
      });
      schoolEvents = normalized;
    })();

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

      eventsList.innerHTML = '';

      if (events.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'No school events scheduled for this day.';
        li.className = 'text-gray-500 italic';
        eventsList.appendChild(li);
      } else {
        events.forEach(event => {
          const li = document.createElement('li');
          // Student view is read-only: no delete button included
          li.innerHTML = `<strong>${event.title}</strong>`;
          eventsList.appendChild(li);
        });
      }
    };

    const showMessage = (message, type) => {
      // keep the small message box as informational (if any)
      if (!messageBox) return;
      messageBox.textContent = message;
      messageBox.className = `message-box message-${type}`;
      messageBox.style.display = 'block';

      setTimeout(() => {
        messageBox.style.display = 'none';
      }, 4000);
    };

    prevMonthButton.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() - 1);
      renderCalendar();
    });

    nextMonthButton.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
      renderCalendar();
    });

    renderCalendar();
  });

  const yearSpan = document.getElementById('year');
  if (yearSpan) yearSpan.textContent = new Date().getFullYear();
  </script>
</body>
</html>
