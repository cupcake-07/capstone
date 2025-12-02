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
        * { box-sizing: border-box; }
        *, body { margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            overflow-x: hidden;
            font-family: 'Montserrat', 'Segoe UI', Tahoma, sans-serif;
            font-size: 16px;
            color: var(--lblack);
            line-height: 1.6;
            background: linear-gradient(var(--lblue), var(--purple)) center/cover no-repeat fixed, url(Images/school13.jpg) center/cover no-repeat fixed;
            background-attachment: fixed;
        }
        a { text-decoration: none; color: var(--white); }
        
        ::-webkit-scrollbar { width: 8px; background: var(--black); }
        ::-webkit-scrollbar-thumb { background: var(--Dwhite); box-shadow: inset 0 0 5px rgba(0,0,0,0.5); }
        
        #top { margin-top: 0; }
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
        div { display: block; unicode-bidi: isolate; }
        .side-link, .welcome button { position: absolute; cursor: pointer; }
        .welcome button {
            width: 25px; height: 20px; border: 0; outline: 0; background: 0 0;
            color: var(--white); right: 25px; top: 10px; padding: 2px 0;
            line-height: 16px; font-size: 14px; font-weight: 700; transition: .3s;
        }
        .welcome button:hover { background: rgba(214, 22, 22, .9); }
        
        /* Header and navigation */
        .header { width: 100vw; height: auto; transition: .5s; animation: 18s linear infinite show; }
        .header .nav {
            height: 70px;
            background: linear-gradient(180deg, var(--blue) 0%, var(--pink) 100%);
            animation: 1s left;
            width: 100%;
            transition: .4s;
            display: flex;
            align-items: center;
        }
        .header .nav .logo { height: 100%; font-size: 20px; font-family: calibri; margin-left: 50px; padding: 24px 40px; transition: .3s; animation: 2.5s left; display: flex; align-items: center; }
        .header .nav .logo img { height: 65px; width: auto; }
        .header .nav .menu { width: 50%; height: 100%; font-size: 20px; margin: 0 10px 0 360px; transition: .3s; animation: 1.8s top; }
        .header .nav .menu ul { width: 100%; height: 100%; list-style: none; display: flex; align-items: center; justify-content: center; }
        .header .nav .menu ul li { font-weight: 100px; margin: 6px 0 0 25px; text-transform: uppercase; font-size: 12px; font-family: sans-serif; letter-spacing: 1.2px; word-spacing: 1.3px; }
        .header .nav .menu ul li a { color: #ffffff; padding: 22px 6px; transition: .4s; border-bottom: 2px solid transparent; }
        .header .nav .menu ul li a:hover { color: navy; border-bottom: 2px solid navy; font-weight: 700; }
        
        /* How section */
        .how {
            width: 100%;
            height: auto;
            background: linear-gradient(var(--lblue), var(--purple)) center/cover no-repeat fixed, url(Images/school13.jpg) center/cover no-repeat fixed;
            padding: 50px 18px 80px;
            text-align: center;
            color: #333;
            display: block;
        }
        .how h1 { color: #ffffff; font-family: 'Montserrat', sans-serif; margin-bottom: 30px; font-weight: 700; font-size: 2rem; }
        .how .box {
            width: 300px;
            min-width: 240px;
            height: auto;
            background: #fff;
            margin: 18px;
            padding: 22px 18px;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
            will-change: transform;
            transform-origin: center center;
            border-radius: 10px;
            display: inline-block;
            vertical-align: top;
        }

        .how .box .icon {
            width: 68px;
            height: 68px;
            background: linear-gradient(180deg, var(--blue), #2563eb);
            border-radius: 50%;
            margin: 0 auto 14px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 32px;
            box-shadow: 0 4px 12px rgba(61, 133, 248, 0.3);
        }

        .how .box h2 { 
            font-size: 1.1rem; 
            color: var(--lblack); 
            margin: 8px 0 8px; 
            font-weight: 600;
        }

        .how .box p { 
            color: #444; 
            font-size: 0.95rem; 
            line-height: 1.45; 
            margin: 6px 0; 
        }

        .how .box:hover { 
            transform: translateY(-12px) scale(1.18);
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease-in-out;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--white);
            padding: 40px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.4s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--blue);
            padding-bottom: 15px;
        }

        .modal-header h2 {
            color: var(--blue);
            font-size: 1.8rem;
            margin: 0;
        }

        .modal-close {
            font-size: 28px;
            font-weight: bold;
            color: var(--grey);
            cursor: pointer;
            background: none;
            border: none;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: var(--pink);
        }

        .modal-body {
            color: var(--lblack);
            line-height: 1.8;
            font-size: 1rem;
        }

        .modal-image {
            width: 100%;
            height: 250px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--blue), var(--pink));
            overflow: hidden;
        }

        .modal-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .modal-image i {
            font-size: 80px;
            color: var(--white);
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .page-load-fade { animation: fadeIn 0.8s ease-in-out forwards; opacity: 1; }
        .page-load-slide { animation: fadeInUp 0.8s ease-out forwards; opacity: 1; }
        .how.page-load-slide { animation-delay: 0.2s; opacity: 0; }
        .how .box.page-load-slide { animation-delay: 0.3s; opacity: 0; }

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
        .site-footer .footer-col { flex: 1 1 220px; min-width: 200px; }
        .footer-col .footer-logo img { height: 56px; width: auto; margin-bottom: 8px; }
        .footer-col h4 { color: #fff; margin-bottom: 10px; font-weight: 700; font-size: 1rem; }
        .footer-col p, .footer-col li, .footer-col a { color: rgba(255,255,255,0.92); line-height: 1.5; }
        .footer-links ul { list-style: none; padding: 0; margin: 0; }
        .footer-links li { margin: 6px 0; }
        .footer-links a { color: rgba(255,255,255,0.95); text-decoration: none; transition: color .18s ease; }
        .footer-links a:hover { color: var(--lyellow); }
        .social-icons a { display: inline-block; background: rgba(255,255,255,0.08); padding: 8px 10px; border-radius: 6px; margin-right: 8px; color: var(--white); font-size: 18px; transition: transform .15s ease, background .15s ease; }
        .social-icons a:hover { transform: translateY(-3px); background: rgba(255,255,255,0.14); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.06); margin-top: 20px; padding-top: 16px; text-align: center; color: rgba(255,255,255,0.85); font-size: 0.95rem; }

        /* Responsive */
        @media (max-width: 992px) {
            .header .nav { flex-direction: column; align-items: center; height: auto; padding: 8px 10px; }
            .header .nav .logo { margin-left: 0; padding: 6px 0; }
            .header .nav .logo img { max-height: 60px; }
            .header .nav .menu { width: 100%; margin: 8px 0 0 0; font-size: 16px; }
            .header .nav .menu ul { flex-wrap: wrap; gap: 6px; }
            .header .nav .menu ul li a { padding: 8px 10px; display: inline-block; }
        }
        @media (max-width: 780px) {
            .how .box { width: 92%; max-width: 520px; margin: 14px auto; display: block; }
            .how h1 { font-size: 1.6rem; }
            .site-footer .inner { gap: 16px; }
            .footer-col { min-width: 100%; }
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
                text-align: center;
            }
            .header .nav .menu ul li a {
                width: auto;
                padding: 10px;
                font-size: 14px;
                text-align: center;
                display: inline-block;
            }

            /* Mobile navigation centering overrides */
            .header .nav .menu {
                margin: 8px 0 0 0 !important;
                width: 100%;
                display: flex;
                justify-content: center;
            }
            .header .nav .menu ul {
                width: auto;
                align-items: center;
                justify-content: center;
                padding-left: 0;
            }
            
            .welcome {
                display: none;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="welcome" id="top">Welcome to Glorious God's Family Christian School
        <button onclick="cancel()">&#10540</button>
    </div>

    <header class="header">
        <div class="nav container-inner" id="navTop">
            <div class="logo logo-wrapper">
                <img src="Images/g2flogo.png"/>
            </div>
            <div class="menu">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="Event.php">Events</a></li>
                    <li><a href="program.php">Programs</a></li>
                    <li><a href="how.php" class="active">How it works</a></li>
                </ul>
            </div>
        </div>
    </header>

    <section class="how page-load-slide" id="how">
        <h1>How Our Programs Work</h1>
        <div class="box page-load-slide" data-image="Images/enrollment.jpg" data-description="Our Enrollment process is simple and straightforward. Complete the application form with your child's basic information, provide required documents including birth certificate and health records, and submit your application. Our admissions team will review your application and contact you to schedule an enrollment meeting with parents and the child.">
            <div class="icon"><i class="fa fa-pencil-square-o"></i></div>
            <h2>Enrollment</h2>
            <p>Complete the application form and provide required documents to enroll your child.</p>
        </div>
        <div class="box page-load-slide" data-image="Images/orientation.jpg" data-description="The Orientation & Welcome day is a wonderful opportunity for families to get acquainted with our school. You'll meet our dedicated teachers, tour our classrooms and facilities, learn about our daily routines and educational philosophy, and ask questions about your child's transition to our school community.">
            <div class="icon"><i class="fa fa-users"></i></div>
            <h2>Orientation & Welcome</h2>
            <p>Attend an orientation day to meet teachers, visit classrooms, and learn about daily routines.</p>
        </div>
        <div class="box page-load-slide" data-image="Images/learning.jpg" data-description="During the Learn & Participate phase, pupils engage in structured lessons tailored to their developmental level. Students take part in hands-on projects, play-based activities, and after-school clubs. Teachers use diverse teaching methods to ensure all learners can succeed and develop a genuine love for learning.">
            <div class="icon"><i class="fa fa-book"></i></div>
            <h2>Learn & Participate</h2>
            <p>Pupils take part in structured lessons, hands-on projects, play-based activities and clubs.</p>
        </div>
        <div class="box page-load-slide" data-image="Images/progress.jpg" data-description="Progress & Celebrate is our approach to recognizing student growth and achievement. We provide regular progress reports to keep families informed about their child's development, celebrate milestones and successes in the classroom and beyond, and hold special events to recognize academic and personal achievements throughout the school year.">
            <div class="icon"><i class="fa fa-star"></i></div>
            <h2>Progress & Celebrate</h2>
            <p>We provide regular progress reports and celebrate milestones, projects, and achievements.</p>
        </div>
    </section>

    <!-- Modal for How Section -->
    <div class="modal" id="howModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="howModalTitle">Step Title</h2>
                <button class="modal-close" id="closeHowModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-image" id="howModalImage">
                    <i class="fa fa-image"></i>
                </div>
                <p id="howModalDescription">Step description will go here.</p>
            </div>
        </div>
    </div>

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
                <p>Block 6, Lot 1 & 2, Bari St., Cluster 2, Bella Vista Subdivision, Brgy. Santiago, General Trias, Philippines</p>
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
        window.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('page-load-fade');
            const howSection = document.querySelector('.how');
            if(howSection) howSection.classList.add('page-load-slide');
        });

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

        // Modal functionality for How Section
        const howBoxes = document.querySelectorAll('.how .box');
        const howModal = document.getElementById('howModal');
        const howModalTitle = document.getElementById('howModalTitle');
        const howModalDescription = document.getElementById('howModalDescription');
        const howModalImage = document.getElementById('howModalImage');
        const closeHowModal = document.getElementById('closeHowModal');

        howBoxes.forEach(box => {
            box.addEventListener('click', () => {
                const title = box.querySelector('h2').innerText;
                const shortDescription = box.querySelector('p').innerText;
                const fullDescription = box.getAttribute('data-description') || shortDescription;
                const imageSrc = box.getAttribute('data-image');

                howModalTitle.innerText = title;
                howModalDescription.innerText = fullDescription;
                
                if(imageSrc) {
                    howModalImage.innerHTML = `<img src="${imageSrc}" alt="${title}">`;
                } else {
                    const iconElement = box.querySelector('.icon i');
                    const iconClass = iconElement ? iconElement.className : 'fa fa-image';
                    howModalImage.innerHTML = `<i class="${iconClass}"></i>`;
                }

                howModal.classList.add('show');
            });
        });

        closeHowModal.addEventListener('click', () => {
            howModal.classList.remove('show');
        });

        window.addEventListener('click', (e) => {
            if (e.target === howModal) {
                howModal.classList.remove('show');
            }
        });

        (function setYear() {
            var y = new Date().getFullYear();
            var el = document.getElementById('currentYear');
            if (el) el.textContent = y;
        })();
    </script>
</body>
</html>