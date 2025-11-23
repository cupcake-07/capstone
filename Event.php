<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Hope Academy | Community Outreach</title>
    <style>
        /* --- CSS VARIABLES & RESET --- */
        :root {
            --primary-color: #1a3c7a; /* School Navy */
            --accent-color: #f4a261;  /* Warm Gold/Orange */
            --text-dark: #333;
            --text-light: #fff;
            --bg-light: #f9f9f9;
            --transition-speed: 0.8s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            overflow-x: hidden; /* Prevent horizontal scroll */
            background-color: var(--bg-light);
        }

        /* --- NAVIGATION --- */
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: linear-gradient(90deg, rgba(253, 253, 253, 0.98) 0%, #f0a5e6 100%);
            padding: 1.2rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo {
            font-weight: bold;
            font-size: 1.5rem;
            color: var(--primary-color);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            margin-left: 2rem;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--accent-color);
        }

        .cta-btn {
            background-color: var(--primary-color);
            color: var(--text-light) !important;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
        }

        .cta-btn:hover {
            background-color: #132c5a;
        }

        /* --- HERO SECTION (SLIDING BACKGROUND) --- */
        .hero {
            position: relative;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: var(--text-light);
            padding: 0 20px;
            overflow: hidden;
        }

        /* The Dark Blue Overlay */
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(26, 60, 122, 0.85), rgba(26, 60, 122, 0.5));
            z-index: 1;
        }

        /* Container for the sliding images */
        .hero-slider {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        /* Individual Slide Styling */
        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transform: scale(1);
            animation: slideAnimation 15s infinite;
        }

        /* Slide Images & Delays */
        .slide:nth-child(1) { 
            background-image: url('Images/school12.jpg');
            animation-delay: 0s; 
        }
        .slide:nth-child(2) { 
            background-image: url('Images/school13.jpg');
            animation-delay: 5s; 
        }
        .slide:nth-child(3) { 
            background-image: url('Images/school21.jpg');
            animation-delay: 10s; 
        }

        /* Keyframes for Background Slider (Fade + Zoom) */
        @keyframes slideAnimation {
            0% { opacity: 0; transform: scale(1); }
            5% { opacity: 1; }
            33% { opacity: 1; }
            38% { opacity: 0; transform: scale(1.1); }
            100% { opacity: 0; }
        }

        /* Content Styling */
        .hero-content {
            position: relative;
            z-index: 2;
        }

        /* Hero Text Load Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            opacity: 0;
            animation: fadeInUp 1s ease-out forwards;
            animation-delay: 0.2s;
        }

        .hero p {
            font-size: 1.2rem;
            max-width: 600px;
            margin-bottom: 2rem;
            margin-left: auto;
            margin-right: auto;
            opacity: 0;
            animation: fadeInUp 1s ease-out forwards;
            animation-delay: 0.6s;
        }

        .hero-btn {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            background-color: var(--accent-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            opacity: 0;
            animation: fadeInUp 1s ease-out forwards;
            animation-delay: 1s;
            transition: transform 0.3s, background-color 0.3s;
        }

        .hero-btn:hover {
            transform: scale(1.05);
            background-color: #e08e50;
        }

        /* --- ALBUM SECTION (replaces modal) --- */
        .album-section {
            background-color: var(--bg-light);
            padding: 5rem 10%;
        }

        .album-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .album-header {
            background: var(--primary-color);
            color: #fff;
            padding: 2rem;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 3rem;
        }

        .album-title {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .album-sub {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .album-body {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        .album-left {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .album-preview {
            width: 100%;
            height: 400px;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .album-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            border-radius: 8px;
            width: 100%;
            height: 100%;
        }

        .album-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.75rem;
            width: 100%;
        }

        .album-thumb {
            background: #f0f0f0;
            border: 2px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            overflow: hidden;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100px;
            transition: all 0.3s;
        }

        .album-thumb img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .album-thumb:hover {
            border-color: var(--accent-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .album-thumb:hover img {
            transform: scale(1.1);
        }

        .album-thumb.active {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(26, 60, 122, 0.3);
        }

        .album-right {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            color: var(--text-dark);
            height: fit-content;
        }

        .album-right h3 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            border-bottom: 3px solid var(--accent-color);
            padding-bottom: 0.5rem;
        }

        .album-right ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 2rem;
        }

        .album-right li {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
            line-height: 1.6;
        }

        .album-right li:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .album-right li strong {
            color: var(--primary-color);
        }

        .album-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .album-cta {
            background-color: var(--primary-color);
            color: #fff;
            padding: 1rem;
            border-radius: 4px;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.3s;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .album-cta:hover {
            background-color: #132c5a;
        }

        /* --- FOOTER STYLING --- */
        .footer {
            background-color: #1a1a1a;
            color: #fff;
            padding: 3rem 5%;
            text-align: center;
        }

        .footer h1 {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: rgb(108, 120, 230)
        }

        .footer .section {
            max-width: 1200px;
            margin: 0 auto 3rem;
        }

        .footer .content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            text-align: left;
            margin-bottom: 2rem;
        }

        .footer ul {
            list-style: none;
            padding: 0;
        }

        .footer ul h4 {
            color: rgb(92, 160, 223)
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .footer ul li {
            margin-bottom: 0.5rem;
            line-height: 1.6;
            color: #aaa;
        }

        .footer ul li a {
            color: #aaa;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer ul li a:hover {
            color: var(--accent-color);
        }

        /* --- SOCIAL MEDIA ICONS --- */
        .link {
            margin: 2rem 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .link h4 {
            color: var(--accent-color);
            font-size: 1.2rem;
            margin: 0;
        }

        .link a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background-color: var(--primary-color);
            color: #fff;
            border-radius: 50%;
            text-decoration: none;
            font-size: 1.5rem;
            transition: all 0.3s;
            margin: 0 0.5rem;
        }

        .link a:hover {
            background-color: var(--accent-color);
            transform: scale(1.1);
            color: #000;
        }

        .link a i {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* --- TOP ARROW --- */
        .top-arrow {
            margin-top: 2rem;
        }

        .top-arrow a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background-color: var(--accent-color);
            color: #000;
            border-radius: 50%;
            text-decoration: none;
            font-size: 1.5rem;
            transition: all 0.3s;
        }

        .top-arrow a:hover {
            background-color: #fff;
            transform: translateY(-5px);
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .mission-container { grid-template-columns: 1fr; }
            .nav-links { display: none; }
            .album-body { grid-template-columns: 1fr; }
            .album-left { width: 100%; }
            .album-preview { height: 300px; }
            .album-title { font-size: 2rem; }
            .section-title { font-size: 2rem; }
            .footer .content { grid-template-columns: 1fr; }
            .link a {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav>
        <div class="logo">Glorious God's Family Christian School</div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="program.php">Programs</a>
             <a href="Event.php">Events</a>
             <a href="index.php">Contact Us</a>
             
             
            
            
            
        </div>
    </nav>

    <!-- Hero Section (Sliding Background + Content) -->
    <header class="hero">
        <!-- Background Slider -->
        <div class="hero-slider">
            <div class="slide"></div>
            <div class="slide"></div>
            <div class="slide"></div>
        </div>

        <!-- Overlay -->
        <div class="hero-overlay"></div>

        <!-- Text Content -->
        <div class="hero-content">
            <h1>Empowering The Next Generation</h1>
            <p>Join the Hope Academy Outreach Program. We bring education, supplies, and smiles to remote communities.</p>
            <a href="#album" class="hero-btn">See Our Work</a>
        </div>
    </header>

    <!-- Mission Section (Scroll Animation) -->
    <section id="about">
        <div class="mission-container">
           
            
        </div>
    </section>

    <!-- Stats Section (Scroll Animation) -->
    

    <!-- Programs Section (Scroll Animation) -->
   
    <!-- Album & Achievements Section (NEW - replaces modal) -->
    <section id="album" class="album-section">
        <div class="album-container">
            <div class="album-header reveal">
                <h2 class="album-title">School Album</h2>
                <p class="album-sub">A showcase of community events, student achievements, and highlights from our school .</p>
            </div>

            <div class="album-body reveal delay-100">
                <div class="album-left">
                    <div class="album-preview" id="albumPreview" aria-live="polite">
                        <img src="Images/fieldtrip.jpg" alt="Preview image" id="albumPreviewImg">
                    </div>
                    <div class="album-grid" id="albumGrid" role="list">
                        <button class="album-thumb active" data-src="Images/fieldtrip.jpg" aria-label="Open image 1" role="listitem">
                            <img src="Images/fieldtrip.jpg" onerror="this.src='Images/fieldtrip.jpg'" alt="Event 1">
                        </button>
                        <button class="album-thumb" data-src="Images/recog.jpg" aria-label="Open image 2" role="listitem">
                            <img src="Images/recog.jpg" onerror="this.src='Images/recog.jpg'" alt="Event 2">
                        </button>
                        <button class="album-thumb" data-src="Images/yearend.jpg" aria-label="Open image 3" role="listitem">
                            <img src="Images/yearend.jpg" onerror="this.src='Images/yearend.jpg'" alt="Event 3">
                        </button>
                        <button class="album-thumb" data-src="Images/school18.jpg" aria-label="Open image 4" role="listitem">
                            <img src="Images/school18_thumb.jpg" onerror="this.src='Images/school18.jpg'" alt="Event 4">
                        </button>
                        <button class="album-thumb" data-src="Images/school19.jpg" aria-label="Open image 5" role="listitem">
                            <img src="Images/school19_thumb.jpg" onerror="this.src='Images/school19.jpg'" alt="Event 5">
                        </button>
                        <button class="album-thumb" data-src="Images/schoolgrad.jpg" aria-label="Open image 6" role="listitem">
                            <img src="Images/schoolgrad.jpg" onerror="this.src='Images/schoolgrad.jpg'" alt="Event 6">
                        </button>
                    </div>
                </div>

                <aside class="album-right" aria-label="Achievements">
                    <h3>Events</h3>
                    <ul>
                        <li><strong>Field Trip (February 2019)</strong> — Students participated in a hands-on educational tour to explore nature and history firsthand.</li>
                        <li><strong>Year-end Party (December 2022)</strong> — Celebrated student achievements with games,  food, and awards recognizing.</li>
                        <li><strong>Buwan ng Wika (August 2017)</strong> — Celebrating Buwan ng wika.</li>
                        <li><strong>Film Showing(Dec 2024)</strong> — Memorable Film Showing</li>
                        <li><strong>Foundation Day (March 2020)</strong> — Promoting teamwork and healthy lifestyles through athletic competitions and wellness activities.</li>
                    </ul>
                   
                </aside>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    

   <footer class="footer" id="contact_us">
           <h1>Contact us...</h1>
          <section class="section">
              <div class="content">
                 <ul>
                    <h4>Contact</h4>
                    <li>Glorious God's Family Christian School</li>
                    <li>123 Community St., (Your City)</li>
                    <li>Phone: +46 40 902 58</li>
                    <li>Email: <a href="mailto:arnel.shin@gmail.com">arnel.shin@gmail.com</a></li>
                 </ul>

                 <ul>
                    <h4>Quick Links</h4>
                    <li><a href="#about_us">About</a></li>
                    <li><a href="#mission">Mission & Vision</a></li>
                    <li><a href="#job_criteria">Programs</a></li>
                    
                 </ul>

                 <!-- optional third column for office hours -->
                 <ul>
                    <h4>Office Hours</h4>
                    <li>Mon–Fri: 07:00 – 17:00</li>
                    <li>Sat: 10:00 – 12:00</li>
                    <li>Sun: Closed</li>
                 </ul>
              </div>
          </section>

             
 
             <div class="top-arrow" id="navTop">
                <a href="#top"><i class="fa fa-angle-double-up"></i></a>	 
             </div>
 
        </footer>
 
    <!-- SCROLL TRIGGER SCRIPT -->
    <script>
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.15 
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    observer.unobserve(entry.target); 
                }
            });
        }, observerOptions);

        const elementsToReveal = document.querySelectorAll('.reveal');
        elementsToReveal.forEach(el => {
            observer.observe(el);
        });

        // Album thumbnail gallery
        (function() {
            const previewImg = document.getElementById('albumPreviewImg');
            const thumbs = Array.from(document.querySelectorAll('.album-thumb'));

            thumbs.forEach(btn => {
                btn.addEventListener('click', function() {
                    const src = this.getAttribute('data-src');
                    if(src && previewImg) {
                        previewImg.src = src;
                        previewImg.alt = this.querySelector('img').alt || 'Large preview';
                        
                        // Update active state
                        thumbs.forEach(t => t.classList.remove('active'));
                        this.classList.add('active');
                    }
                });
            });

            // Arrow key navigation
            document.addEventListener('keydown', function(e) {
                const current = thumbs.findIndex(t => t.classList.contains('active'));
                if(current === -1) return;
                
                let next = current;
                if(e.key === 'ArrowLeft') next = (current - 1 + thumbs.length) % thumbs.length;
                if(e.key === 'ArrowRight') next = (current + 1) % thumbs.length;
                
                if(next !== current && thumbs[next]) {
                    thumbs[next].click();
                    thumbs[next].focus();
                }
            });
        })();
    </script>
</body>
</html>