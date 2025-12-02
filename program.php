<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student program</title>
    <link rel="icon" href="Images/future.png">
    <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
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
            color: #ffffffff;
            line-height: 1.6;
            background: linear-gradient(var(--lblue), var(--purple)) center/cover no-repeat fixed, url(Images/school13.jpg) center/cover no-repeat fixed;
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
        side-link, welcome button {
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
            background: #0a0927;
            width: 40px;
            height: 40px;
            font-size: 18px;
            border-radius: 6px;
            margin: 7px 5px;
            display: block;
            padding: 10px 0;
            text-align: center;
            line-height: 20px;
            border: 1px solid #708090;
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
            /* changed: pinkish background left-to-right with new second color */
            background: linear-gradient(180deg, #3d85f8 0%, #fd4ba7 100%);
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
            color: #ffffffff;
            padding: 22px 6px;
            transition: .4s;
            border-bottom: 2px solid transparent;
        }
        .header .nav .menu ul li a:hover {
            color: navy;
            border-bottom: 2px solid navy;
            font-weight: 700;
        }
                .career-section {
            width: 100%;
            height: auto;
            background: transparent;
            padding: 30px 0 50px;
            text-align: center;
        }
        .career-section h1 {
            padding: 30px;
            color: #ffffff;
        }
        .career-section p {
            margin: 30px 0;
            height: auto;
            padding: 10px 30px;
        }
        .career-section p em {
            padding: 0;
            color: #ffffff;
            font-size: 25px;
            font-weight: bold;
            font-family: calibri;
            word-spacing: 1px;
        }
        .career-section .sect {
            width: 100%;
            height: auto;
            margin-top: 20px;
            margin-bottom: 60px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }
        .career-section .sect .box {
            width: 260px;
            height: auto;
            background: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
            margin: 15px 30px;
            padding: 20px 12px;
            text-align: center;
            cursor: pointer;
            transition: .3s;
            transform: scale(1);
        }
        .career-section .sect .box:hover {
            transform: scale(1.09);
            box-shadow: inset .5px 1px 5px var(--grey);
        }
        .career-section .sect .box .icon,
        .how .box .icon {
            width: 80px;
            height: 80px;
            background: var(--lblue);
            border-radius: 50%;
            margin: auto;
        }
        .career-section .sect .box .icon i {
            color: var(--white);
            font-size: 35px;
            line-height: 80px;
        }
        .career-section .sect .box .icon .fa-lightbulb-o {
            font-size: 43px;
        }
        .career-section .sect .box .icon .fa-trophy,
        .footer h1 {
            font-size: 38px;
        }
        .career-section .sect .box h2 {
            margin: 15px 0;
            padding: 5px;
            color: var(--black);
            display: block;
        }
        .career-section .sect .box p,
        .how .box p {
            margin: 0;
            padding: 5px 13px;
            color: var(--grey);
            display: block;
        }
        /* Skill section */
        .skill-section {
            width: 100vw;
            height: auto;
            padding: 20px 15px 39px;
            margin: 0;
            color: var(--white);
            text-align: center;
            background: transparent;
        }
        .skill-section h1 {
            padding: 6px 30px;
            margin-top: 20px;
            text-align: left;
        }
        .skill-section p {
            margin: 0 0 40px;
            padding: 10px 30px;
            text-align: left;
            letter-spacing: .3px;
            font-family: calibri;
        }
        .skill-section .skill-box {
            transform: scale(1);
            display: inline-block;
            width: 310px;
            height: 280px;
            padding: 20px 10px;
            color: var(--black);
            margin: 15px 20px;
            background: linear-gradient(var(--white), var(--white));
            border-radius: 7px;
            text-align: center;
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0 0 7px rgba(0, 0, 0, .5);
            transition: .5s;
        }
        .skill-section .skill-box:hover {
            transform: scale(.94);
            background: linear-gradient(30deg, navy, var(--lblue));
            box-shadow: 0 0 10px rgba(0, 0, 0, .6);
        }
        .skill-section .skill-box h2 {
            border: 0;
            text-align: center;
            margin: 13px 0;
            letter-spacing: .6px;
            transition: .5s;
            color: var(--grey);
        }
        .skill-section .skill-box p {
            color: var(--grey);
            border: 0;
            text-align: center;
            margin: 0 0 10px;
            padding: 2px 20px;
            font-size: 16px;
            transition: .5s;
        }
        .skill-section .skill-box:hover p {
            color: var(--white);
            font-size: 15px;
        }
        .skill-section .skill-box:hover h2 {
            color: var(--white);
            font-size: 24px;
        }
        .skill-section .skill-box span {
            width: 45px;
            height: 45px;
            display: block;
            font-size: 20px;
            font-family: calibri;
            font-weight: 700;
            border-radius: 50%;
            line-height: 45px;
            color: var(--white);
            background: navy;
            text-align: center;
            margin: 15px auto auto;
            transition: .6s;
        }
        .skill-section .skill-box:hover span {
            background: var(--white);
            color: navy;
            margin-top: 8px;
            transform: rotate(-360deg) scale(.8);
        }
        .skill-section .skill-box button {
            width: 140px;
            border: 0;
            border-radius: 3px;
            letter-spacing: 1px;
            font-size: 11px;
            font-weight: 700;
            outline: 0;
            padding: 12px 20px;
            background: var(--white);
            box-shadow: inset 1px .5px 5px rgba(0, 0, 0, .8);
            color: navy;
            cursor: pointer;
            margin-top: 0;
            transform: translateY(60px);
            transition: .5s;
        }
        .skill-section .skill-box:hover button {
            transform: translateY(9px);
        }
        .skill-section .skill-box button:hover {
            box-shadow: inset 1px 1.5px 6px rgba(0, 0, 0, .9);
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

        @media (max-width: 480px) {
            .modal-content {
                width: 95%;
                padding: 25px;
            }

            .modal-header h2 {
                font-size: 1.4rem;
            }
        }

        /* Page Load Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .page-load-fade {
            animation: fadeIn 0.8s ease-in-out forwards;
            opacity: 1;
        }

        .page-load-slide {
            animation: fadeInUp 0.8s ease-out forwards;
            opacity: 1;
        }

        .career-section.page-load-slide {
            animation-delay: 0.2s;
            opacity: 0;
        }

        .skill-section.page-load-slide {
            animation-delay: 0.4s;
            opacity: 0;
        }

        /* Responsive navigation for program.php — copy of Event.php responsive nav rules */
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
                color: #ffffffff;
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
            .welcome {
                display: none;
            }
        }
    </style>
        
	
	<!-- Keep Google Fonts link; moved CSS rules to style.css -->
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
	
    <div class="welcome" id="top">Welcome to Glorious God's Family Christian School
         <button onclick="cancel()">&#10540</button>
	</div>

	<!-------------------header_start--------------------->
    <header class="header">
        <!-- Add id and container class so sticky JS works the same -->
        <div class="nav container-inner" id="navTop">
            <div class="logo logo-wrapper">
                <img src="Images/g2flogo.png"/>
            </div>
             
   	  	    <div class="menu">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    
                    <li><a href="Event.php">Events</a></li>
                    <li><a href="program.php" class="active">Programs </a></li>
                    <li><a href="how.php">how it works</a></li>
                    
                </ul>
   	  	    </div>
   	    </div>
    </header>
</head>
<body>
    
  <!------------skills_start---------------->
    <div class="career-section page-load-slide" id="career">
    	<h1 style="font-family:Montserrat;">Student Programs</h1>
          
            <div class="sect">

                <div class="box" data-image="Images/school31.jpg" data-description="Our Academic Programs provide strong foundational lessons in reading, writing, and math. Students engage with hands-on activities and age-appropriate technology to build learning confidence. We focus on critical thinking, problem-solving, and fostering a love for learning through interactive and engaging lessons.">
                    <div class="icon"><i class="fa fa-graduation-cap"></i></div>
                    <h2>Academic Programs</h2>
                    <p>Strong foundational lessons in reading, writing, and math, using hands-on activities and age‑appropriate technology to build learning confidence.</p>
                </div>

                <div class="box" data-image="Images/school32.jpg" data-description="Our Clubs & Activities offer a diverse range of after-school programs including arts, music, and sports. These programs foster creativity, teamwork, and healthy habits while allowing students to explore their interests and develop new skills in a supportive environment.">
                    <div class="icon"><i class="fa fa-music"></i></div>
                    <h2>Clubs & Activities</h2>
                    <p>After-school clubs, arts, music, and sports programs that foster creativity, teamwork, and healthy habits.</p>
                </div>

                <div class="box" data-image="Images/school33.jpg" data-description="We celebrate Student Achievements throughout the year by recognizing academic progress, character development, and personal accomplishments. Our awards and recognition programs inspire students to strive for excellence and celebrate their successes with the school community.">
                    <div class="icon"><i class="fa fa-trophy"></i></div>
                    <h2>Student Achievements</h2>
                    <p>Recognition for academic progress, character awards, and celebrations of student accomplishments throughout the year.</p>
                </div>

                <div class="box" data-image="Images/school34.jpg" data-description="Our Student Support services provide comprehensive guidance and counseling to nurture each child's social and emotional well-being and also implementing feeding programs. We facilitate family engagement through parent workshops and maintain open communication to ensure every student receives the care and support they need to thrive.">
                    <div class="icon"><i class="fa fa-heart"></i></div>
                    <h2>Student Support</h2>
                    <p>Guidance, counseling, and family engagement to nurture each child's social and emotional well‑being.</p>
                </div>
            </div>
       
    	 
    	<p class="para"><em>"Glorious God's Family — nurturing hearts and minds for a brighter tomorrow."</em></p>

    </div>
    <div class="skill-section page-load-slide" id="job_criteria">
   	    <h1 style="font-family: Montserrat; text-align:center;">Programs & Activities</h1>
    
        <div class="skill-box" data-image="Images/reading.jpg" data-description="Our Reading & Literacy program focuses on developing strong foundational reading skills through fun, guided sessions and phonics instruction. Students learn at their own pace with interactive activities that help them recognize letters, decode words, and comprehend stories. We create a love for reading that extends beyond the classroom.">
            <span>1</span>
            <h2>Reading & Literacy</h2>
            <p>Fun, guided reading sessions and phonics instruction to help young readers build strong literacy skills.</p>
        </div>

        <div class="skill-box" data-image="Images/math.jpg" data-description="Math & Numeracy builds fundamental mathematical concepts through hands-on activities and engaging games. Students develop number sense, learn counting strategies, and practice basic problem-solving in a fun, interactive environment. Our approach makes math accessible and enjoyable for all learners.">
            <span>2</span>
            <h2>Math & Numeracy</h2>
            <p>Hands-on activities and games to build number sense, counting, and basic problem-solving.</p>
        </div>

        <div class="skill-box" data-image="Images/science.jpg" data-description="Science Explorers encourages curiosity and scientific thinking through age-appropriate experiments and hands-on nature projects. Students observe, question, and discover how the world works. Our program sparks a lifelong passion for exploration and understanding the natural environment.">
            <span>3</span>
            <h2>Science Explorers</h2>
            <p>Age-appropriate experiments and nature projects to spark curiosity about the world.</p>
        </div>

        <div class="skill-box" data-image="Images/arts.jpg" data-description="Arts & Crafts provides a creative outlet for self-expression through various art projects and craft activities. Students explore different mediums, develop fine motor skills, and build confidence in their artistic abilities. This program celebrates creativity and individual expression.">
            <span>4</span>
            <h2>Arts & Crafts</h2>
            <p>Creative art projects and craft activities that encourage expression and fine motor skills.</p>
        </div>

        <div class="skill-box" data-image="Images/music.jpg" data-description="Music & Movement combines interactive music lessons with movement activities to help students build coordination, rhythm, and musicality. Through singing, dancing, and playing instruments, students develop gross and fine motor skills while enjoying the joy of music and movement.">
            <span>5</span>
            <h2>Music & Movement</h2>
            <p>Interactive music lessons and movement sessions that build coordination and rhythm.</p>
        </div>

        <div class="skill-box" data-image="Images/pe.jpg" data-description="Physical Education promotes health and wellness through fun, safe games and athletic activities. Students learn sportsmanship, teamwork, and the importance of staying active. Our PE program builds confidence, develops motor skills, and fosters a lifelong love of physical activity.">
            <span>6</span>
            <h2>Physical Education</h2>
            <p>Fun, safe games and activities that promote physical health and sportsmanship.</p>
        </div>

        <div class="skill-box" data-image="Images/creative.jpg" data-description="Creative Arts & Imagination Program allows children to explore colors, shapes, and textures in a supportive environment. Through various artistic activities, students build confidence, improve fine motor skills, and develop their unique creative voice. This program celebrates every child's imagination and artistic potential.">
            <span>7</span>
            <h2>Creative Arts & Imagination Program</h2>
            <p>Children explore colors, shapes, and textures while building confidence and improving their fine motor skills.</p>
        </div>

        <div class="skill-box" data-image="Images/library.jpg" data-description="Library & Storytime fosters a love for reading and literacy through daily story sessions and library time. Students develop listening skills, expand their vocabulary, and discover new worlds through books. Our program cultivates a deep appreciation for literature and reading.">
            <span>8</span>
            <h2>Library & Storytime</h2>
            <p>Daily story sessions and library time to develop listening skills and a love for books.</p>
        </div>
    </div>

   <center> <footer>
         <div class="bottom"><i class="fa fa-copyright"></i> 2025 Glorious God's Family Christian School || all rights reserved.</div>
    </footer></center>

    <!-- Modal for Career Section -->
    <div class="modal" id="programModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Program Title</h2>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-image" id="modalImage">
                    <i class="fa fa-image"></i>
                </div>
                <p id="modalDescription">Program description will go here.</p>
            </div>
        </div>
    </div>

    <!-- Modal for Skill Section -->
    <div class="modal" id="skillModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="skillModalTitle">Activity Title</h2>
                <button class="modal-close" id="closeSkillModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-image" id="skillModalImage">
                    <i class="fa fa-image"></i>
                </div>
                <p id="skillModalDescription">Activity description will go here.</p>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        // Page load animation trigger
        window.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('page-load-fade');
            const careerSection = document.querySelector('.career-section');
            const skillSection = document.querySelector('.skill-section');
            
            if(careerSection) careerSection.classList.add('page-load-slide');
            if(skillSection) skillSection.classList.add('page-load-slide');
        });

        function cancel() {
            const topEl = document.getElementById('top');
            if (topEl) {
                topEl.style.marginTop = '-50px';
            }
        }

        // Toggle sticky nav on scroll
        window.addEventListener("scroll", function () {
            var top = document.getElementById('navTop');
            if (top) {
                top.classList.toggle("sticky", window.scrollY > 250);
            }
        });

        // Modal functionality for Career Section
        const boxes = document.querySelectorAll('.career-section .box');
        const modal = document.getElementById('programModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalDescription = document.getElementById('modalDescription');
        const modalImage = document.getElementById('modalImage');
        const closeModal = document.getElementById('closeModal');

        boxes.forEach(box => {
            box.addEventListener('click', () => {
                const title = box.querySelector('h2').innerText;
                const shortDescription = box.querySelector('p').innerText;
                const fullDescription = box.getAttribute('data-description') || shortDescription;
                const imageSrc = box.getAttribute('data-image');

                modalTitle.innerText = title;
                modalDescription.innerText = fullDescription;
                
                if(imageSrc) {
                    modalImage.innerHTML = `<img src="${imageSrc}" alt="${title}">`;
                } else {
                    const iconElement = box.querySelector('.icon i');
                    const iconClass = iconElement ? iconElement.className : 'fa fa-image';
                    modalImage.innerHTML = `<i class="${iconClass}"></i>`;
                }

                modal.classList.add('show');
            });
        });

        closeModal.addEventListener('click', () => {
            modal.classList.remove('show');
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });

        // Modal functionality for Skill Section
        const skillBoxes = document.querySelectorAll('.skill-section .skill-box');
        const skillModal = document.getElementById('skillModal');
        const skillModalTitle = document.getElementById('skillModalTitle');
        const skillModalDescription = document.getElementById('skillModalDescription');
        const skillModalImage = document.getElementById('skillModalImage');
        const closeSkillModal = document.getElementById('closeSkillModal');

        skillBoxes.forEach(box => {
            box.addEventListener('click', () => {
                const title = box.querySelector('h2').innerText;
                const shortDescription = box.querySelector('p').innerText;
                const fullDescription = box.getAttribute('data-description') || shortDescription;
                const imageSrc = box.getAttribute('data-image');

                skillModalTitle.innerText = title;
                skillModalDescription.innerText = fullDescription;
                
                if(imageSrc) {
                    skillModalImage.innerHTML = `<img src="${imageSrc}" alt="${title}">`;
                } else {
                    skillModalImage.innerHTML = `<i class="fa fa-image"></i>`;
                }

                skillModal.classList.add('show');
            });
        });

        closeSkillModal.addEventListener('click', () => {
            skillModal.classList.remove('show');
        });

        window.addEventListener('click', (e) => {
            if (e.target === skillModal) {
                skillModal.classList.remove('show');
            }
        });
    </script>
</body>
</html>