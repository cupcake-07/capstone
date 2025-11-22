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
        <a href="attendance.php">Attendance</a>
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

      <style>
        .calendar-container {
          max-width: 1200px;
          margin: 0 auto;
        }

        .calendar-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 24px;
          padding: 0 16px;
        }

        .calendar-header h2 {
          font-size: 24px;
          font-weight: 700;
          color: #0f172a;
          margin: 0;
          flex: 1;
          text-align: center;
        }

        .nav-button {
          background: white;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.2s;
          color: #0f172a;
        }

        .nav-button:hover {
          background: #f1f5f9;
          border-color: #cbd5e1;
        }

        .calendar-grid {
          display: grid;
          grid-template-columns: repeat(7, 1fr);
          gap: 8px;
          margin-bottom: 24px;
          padding: 0 16px;
          background: white;
          padding: 16px;
          border-radius: 12px;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .day-name {
          font-weight: 700;
          text-align: center;
          padding: 12px 8px;
          color: #64748b;
          font-size: 13px;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .calendar-day {
          aspect-ratio: 1;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          cursor: pointer;
          background: white;
          transition: all 0.2s;
          position: relative;
          font-weight: 600;
          font-size: 14px;
          color: #0f172a;
        }

        .calendar-day.empty {
          background: #f8fafc;
          cursor: default;
          border: 1px solid #f0f0f0;
        }

        .calendar-day:hover:not(.empty) {
          background: #f1f5f9;
          border-color: #3b82f6;
          box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
        }

        .calendar-day.today {
          background: #fef3c7;
          border-color: #fbbf24;
        }

        .calendar-day.selected {
          background: #3b82f6;
          color: white;
          border-color: #2563eb;
          box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .calendar-day.has-event::after {
          content: '';
          position: absolute;
          bottom: 4px;
          width: 6px;
          height: 6px;
          background: #fbbf24;
          border-radius: 50%;
        }

        .calendar-day.selected.has-event::after {
          background: white;
        }

        .calendar-content-wrapper {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 24px;
          padding: 0 16px;
        }

        .events-management-card,
        .events-list-card {
          background: white;
          border-radius: 12px;
          padding: 24px;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
          border: 1px solid #e2e8f0;
        }

        .card-title {
          font-size: 16px;
          font-weight: 700;
          color: #0f172a;
          margin-bottom: 16px;
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .event-form {
          display: flex;
          flex-direction: column;
          gap: 12px;
          margin-bottom: 16px;
        }

        .form-group {
          display: flex;
          flex-direction: column;
        }

        .form-group input {
          padding: 12px 14px;
          border: 1.5px solid #e2e8f0;
          border-radius: 8px;
          font-size: 14px;
          font-family: 'Inter', sans-serif;
          transition: all 0.2s;
        }

        .form-group input:focus {
          outline: none;
          border-color: #3b82f6;
          box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .add-event-btn {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          padding: 12px 18px;
          background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
          color: white;
          border: none;
          border-radius: 8px;
          cursor: pointer;
          font-weight: 700;
          font-size: 14px;
          font-family: 'Inter', sans-serif;
          transition: all 0.2s;
          box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        }

        .add-event-btn:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
          background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }

        .add-event-btn:active {
          transform: translateY(0);
        }

        .btn-icon {
          font-size: 16px;
        }

        .btn-text {
          font-weight: 700;
        }

        #message-box {
          padding: 12px 14px;
          border-radius: 8px;
          font-size: 13px;
          display: none;
          margin-top: 12px;
          animation: slideIn 0.3s ease;
        }

        #message-box.message-success {
          background: #d1fae5;
          color: #065f46;
          border: 1px solid #a7f3d0;
        }

        #message-box.message-error {
          background: #fee2e2;
          color: #991b1b;
          border: 1px solid #fecaca;
        }

        @keyframes slideIn {
          from {
            opacity: 0;
            transform: translateY(-8px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        #events-lists {
          list-style: none;
          padding: 0;
          margin: 0;
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        #events-lists li {
          padding: 12px 14px;
          background: #f8fafc;
          border-radius: 8px;
          border-left: 4px solid #3b82f6;
          font-size: 14px;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        #events-lists li:first-child {
          color: #94a3b8;
          font-style: italic;
        }

        .delete-event {
          background: none;
          border: none;
          cursor: pointer;
          padding: 4px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #ef4444;
          transition: all 0.2s;
          margin-left: 8px;
        }

        .delete-event:hover {
          color: #dc2626;
          transform: scale(1.1);
        }

        @media (max-width: 900px) {
          .calendar-content-wrapper {
            grid-template-columns: 1fr;
          }

          .calendar-grid {
            gap: 6px;
          }

          .calendar-day {
            font-size: 12px;
          }
        }
      </style>
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
