<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How it works</title>
    <!-- Font Awesome (icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
    <style>
        :root {
            --black: #000;
            --lblack: #222;
            --white: #fff;
            --Dwhite: #f5f5f5;
            --lyellow: #ebf4b6;
            --grey: #708090;
            --blue: #3d85f8;
            --pink: #fd4ba7;
            --lpurple: #d8b3ff;
            --lblue: #1a8fcc;
            --purple: #a3309dcc;
        }
        * {
            box-sizing: border-box;
        }
        *, body {
            margin: 0;
            padding: 0;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            overflow-x: hidden;
            font-family: 'Montserrat', 'Segoe UI', Tahoma, sans-serif;
            font-size: 16px;
            color: var(--lblack);
            line-height: 1.6;
        }
        a {
            text-decoration: none;
            color: var(--white);
        }
        
        ::-webkit-scrollbar {
            width: 8px;
            background: var(--black);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--Dwhite);
            box-shadow: inset 0 0 5px rgba(0,0,0,0.5);
        }
        #top {
            margin-top: 0;
        }
        .welcome {
            position: relative;
            height: 50px;
            text-align: center;
            background: var(--lyellow);
            color: var(--black);
            line-height: 40px;
            font-size: 16px;
            letter-spacing: 1.2px;
            font-family: calibri;
            animation: 3.6s down;
            width: 100%;
            transition: 0.4s;
        }
        div {
            display: block;
            unicode-bidi: isolate;
        }
        .side-link, .welcome button {
            position: absolute;
            cursor: pointer;
        }
        .welcome button {
            position: absolute;
            cursor: pointer;
            width: 25px;
            height: 20px;
            border: 0;
            outline: 0;
            background: 0 0;
            color: var(--white);
            right: 25px;
            top: 10px;
            padding: 2px 0;
            line-height: 16px;
            font-size: 14px;
            font-weight: 700;
            transition: .3s;
        }
        .welcome button:hover {
	        background: rgba(214, 22, 22, .9);
        }
        .side-link,
        .welcome button {
            position: absolute;
            cursor: pointer;
        }
        .side-link {
            top: 240px;
            right: 50px;
            width: auto;
            display: block;
            animation: 3.4s link;
        }
        .side-link a {
            background: var(--black);
            width: 40px;
            height: 40px;
            font-size: 18px;
            border-radius: 6px;
            margin: 7px 5px;
            display: block;
            padding: 10px 0;
            text-align: center;
            line-height: 20px;
            border: 1px solid var(--grey);
            transition: .4s;
        }
        .side-link a:hover {
            border: 0;
            background: linear-gradient(#2499ca, #0b5d80);
            transform: scale(.84);
        }

        /* Header and navigation */
        .header {
            width: 100vw;
            height: auto;
            transition: .5s;
            animation: 18s linear infinite show;
        }
        .header .nav {
            height: 70px;
            background: linear-gradient(180deg, var(--blue) 0%, var(--pink) 100%);
            animation: 1s left;
            width: 100%;
            transition: .4s;
            display: flex;
            align-items: center;
        }
        .header .nav .logo {
            height: 100%;
            font-size: 20px;
            font-family: calibri;
            margin-left: 50px;
            padding: 24px 40px;
            transition: .3s;
            animation: 2.5s left;
            display: flex;
            align-items: center;
        }
        .header .nav .logo img {
            height: 65px;
            width: auto;
        }
        .header .nav .menu {
            width: 50%;
            height: 100%;
            font-size: 20px;
            margin: 0 10px 0 360px;
            transition: .3s;
            animation: 1.8s top;
        }
        .header .nav .menu ul {
            width: 100%;
            height: 100%;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header .nav .menu ul li {
            font-weight: 100px;
            margin: 6px 0 0 25px;
            text-transform: uppercase;
            font-size: 12px;
            font-family: sans-serif;
            letter-spacing: 1.2px;
            word-spacing: 1.3px;
        }
        .header .nav .menu ul li a {
            /* changed: use solid black for maximum readability */
            color: var(--black);
            padding: 22px 6px;
            transition: .4s;
            border-bottom: 2px solid transparent;
        }
        .header .nav .menu ul li a:hover {
            color: navy;
            border-bottom: 2px solid navy;
            font-weight: 700;
        }
        /* How section */
        .how {
            width: 100%;
            height: auto;
            background: linear-gradient(var(--lblue), var(--purple)) center/cover no-repeat fixed, url(Images/school13.jpg) center/cover no-repeat fixed;
            padding: 50px 18px 80px;      /* increase bottom padding for breathing room */
            text-align: center;
            color: #333;                  /* readable text color */
            display: block;
        }
        .how h1 {
            color: #0b3a5b;               /* darker, school-friendly header */
            font-family: 'Montserrat', sans-serif;
            margin-bottom: 30px;
            font-weight: 700;
            font-size: 2rem;
        }
        .how .box {
            width: 300px;                 /* slightly larger to avoid cramped text */
            min-width: 240px;
            height: auto;
            background: #fff;
            margin: 18px;                 /* more breathing room */
            padding: 22px 18px;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .08); /* softer shadow for card separation */
            transition: transform .18s ease, box-shadow .18s ease;
            border-radius: 10px;
            display: inline-block;        /* keep consistent center layout */
            vertical-align: top;
        }
        .how .box .icon {
            width: 68px;
            height: 68px;
            background: linear-gradient(180deg, #1a8fcc, #0a6fa0);
            border-radius: 50%;
            margin: 0 auto 14px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 28px;
        }
        .how .box h2 {
            font-size: 1.1rem;
            color: #112;
            margin: 8px 0 8px;
        }
        .how .box p {
            color: #444;
            font-size: 0.95rem;
            line-height: 1.45;
            margin: 6px 0;
        }
        .how .box:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 26px rgba(0,0,0,0.12);
        }

        /* Footer styles: consistent with header color palette and responsive layout */
        .site-footer {
            background: linear-gradient(180deg, rgba(10,111,160,.95), rgba(58,10,90,.95));
            color: var(--white);
            padding: 36px 18px;
            font-size: 15px;
        }
        .site-footer .inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            gap: 26px;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
        }
        .site-footer .footer-col {
            flex: 1 1 220px;
            min-width: 200px;
        }
        .footer-col .footer-logo img {
            height: 56px;
            width: auto;
            margin-bottom: 8px;
        }
        .footer-col h4 {
            color: #fff;
            margin-bottom: 10px;
            font-weight: 700;
            font-size: 1rem;
        }
        .footer-col p, .footer-col li, .footer-col a {
            color: rgba(255,255,255,0.92);
            line-height: 1.5;
        }
        .footer-links ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .footer-links li {
            margin: 6px 0;
        }
        .footer-links a {
            color: rgba(255,255,255,0.95);
            text-decoration: none;
            transition: color .18s ease;
        }
        .footer-links a:hover {
            color: var(--lyellow);
        }
        .social-icons a {
            display: inline-block;
            background: rgba(255,255,255,0.08);
            padding: 8px 10px;
            border-radius: 6px;
            margin-right: 8px;
            color: var(--white);
            font-size: 18px;
            transition: transform .15s ease, background .15s ease;
        }
        .social-icons a:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.14);
        }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.06);
            margin-top: 20px;
            padding-top: 16px;
            text-align: center;
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
        }
        .back-top {
            display: inline-block;
            margin-left: 8px;
            background: rgba(255,255,255,0.08);
            padding: 8px 10px;
            border-radius: 6px;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
        }
        .back-top:hover { background: rgba(255,255,255,0.12); }

        @media (max-width: 780px) {
            .site-footer .inner {
                gap: 16px;
                padding-bottom: 10px;
            }
            .footer-col {
                min-width: 100%;
            }
            .footer-col .footer-logo img {
                height: 48px;
            }
            .footer-bottom { font-size: 0.88rem; }
        }

        /* Responsive: stack boxes on narrow screens for readability */
        @media (max-width: 780px) {
            .how .box {
                width: 92%;
                max-width: 520px;
                margin: 14px auto;
                display: block;
            }
            .how h1 { font-size: 1.6rem; }
        }

        /* Responsive navigation for how.php — same as Event.php */
        @media (max-width: 992px) {
            .header .nav {
                flex-direction: column;
                align-items: center;
                height: auto;
                padding: 8px 10px;
            }
            .header .nav.container-inner {
                padding-left: 12px;
                padding-right: 12px;
            }
            .header .nav .logo,
            .header .nav .logo.logo-wrapper {
                margin-left: 0;
                padding: 6px 0;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .header .nav .logo img {
                max-height: 60px;
                width: auto;
            }
            .header .nav .menu {
                width: 100%;
                margin: 8px 0 0 0;
                font-size: 16px;
            }
            .header .nav .menu ul {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 6px;
                margin: 0;
                padding: 0;
            }
            .header .nav .menu ul li {
                margin: 6px 6px;
                text-align: center;
            }
            .header .nav .menu ul li a {
                padding: 8px 10px;
                display: inline-block;
                color: var(--black);
            }
        }

        @media (max-width: 480px) {
            .header .nav {
                padding: 6px 8px;
            }
            .header .nav .logo img {
                max-height: 52px;
            }
            .header .nav .menu ul {
                flex-direction: column;
            }
            .header .nav .menu ul li {
                width: 100%;
                margin: 4px 0;
            }
            .header .nav .menu ul li a {
                width: 100%;
                padding: 10px;
                font-size: 14px;
                text-align: center;
            }
        }
    </style>
    <!-- Keep Google Fonts link; moved CSS rules to style.css -->
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
	
</head>
<body>
    <div class="welcome" id="top">Welcome to Glorious God's Family Christian School
         <button onclick="cancel()">&#10540</button>
	</div>

	<!-------------------header_start--------------------->
    <header class="header">
        <!-- Add id and container class for consistent behavior -->
        <div class="nav container-inner" id="navTop">
            <div class="logo logo-wrapper">
                <img src="Images/g2flogo.png"/>
            </div>

            <div class="menu">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="Event.php">Events</a></li>
                    <li><a href="program.php">Programs </a></li>
                    <li><a href="how.php" class="active">How it works</a></li>
                </ul>
            </div>
        </div>
    </header>
    <!-- ...existing content ... -->

    <section class="how" id="how">

    	    <h1>How Our Programs Work</h1>
           <div class="box">
           	 <div class="icon"><i class="fa fa-pencil-square-o"></i></div>
           	 <h2>Enrollment</h2>
           	 <p>Complete the application form and provide required documents to enroll your child.</p>
           </div>

           <div class="box">
           	 <div class="icon"><i class="fa fa-users"></i></div>
           	 <h2>Orientation & Welcome</h2>
           	 <p>Attend an orientation day to meet teachers, visit classrooms, and learn about daily routines.</p>
           </div>

           <div class="box">
           	  <div class="icon"><i class="fa fa-book"></i></div>
           	  <h2>Learn & Participate</h2>
           	 <p>Pupils take part in structured lessons, hands-on projects, play-based activities and clubs.</p>
           </div>

           <div class="box">
           	 <div class="icon"><i class="fa fa-star"></i></div>
           	  <h2>Progress & Celebrate</h2>
           	 <p>We provide regular progress reports and celebrate milestones, projects, and achievements.</p>
           </div>
    </section>

    <!-- Footer: matches look & feel of other pages -->
    <footer class="site-footer">
        <div class="inner">
            <div class="footer-col">
                <div class="footer-logo">
                    <img src="Images/g2flogo.png" alt="Glorious God's Family logo">
                </div>
                <p>Glorious God's Family Christian School - nurturing mind, body, and spirit with a joyful and supportive learning environment.</p>
            </div>

            <div class="footer-col footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="Event.php">Events</a></li>
                    <li><a href="program.php">Programs</a></li>
                    <li><a href="how.php">How it works</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contact</h4>
                <p>
Block 6, Lot 1 & 2, Bari St., Cluster 2, Bella Vista Subdivision, Brgy. Santiago, General Trias, Philippines</p>
                <p>Phone: +46 40 902 58</p>
                <p>Email: <a href="mailto:arnel.shin@gmail.com">arnel.shin@gmail.com</a></p>
                <div style="margin-top:10px;" class="social-icons">
                    <a href="https://www.facebook.com/GloriousgfcsiCluster2" aria-label="Facebook"><i class="fa fa-facebook"></i></a>
                    
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            &copy; <span id="currentYear"></span> Glorious God's Family Christian School. All Rights Reserved.
           
        </div>
    </footer>

    <script type="text/javascript">
        function cancel() {
            const topEl = document.getElementById("top");
            if (topEl) {
                topEl.style.marginTop = '-50px';
            }
        }

        window.addEventListener('scroll', function() {
            var navTopEl = document.getElementById('navTop');
            if (navTopEl) {
                navTopEl.classList.toggle("sticky", window.scrollY > 250);
            }
        });

        // new: smooth scroll to top and current year
        function scrollToTop(event) {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        (function setYear() {
            var y = new Date().getFullYear();
            var el = document.getElementById('currentYear');
            if (el) el.textContent = y;
        })();
    </script>
</body>
</html>