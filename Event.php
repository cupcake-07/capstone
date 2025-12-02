<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student program</title>
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
            --lpink: #fd6bb6;
            --lpurple: #d8b3ff;
            --lblue: #1a8fcc;
            --purple: #a3309dcc;
            --dblue: #1a3c7a; /* School Navy */
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

        /* --- ALBUM SECTION (replaces modal) --- */
        .album-section {
            background-color: var(--Dwhite);
            background: linear-gradient(var(--blue), var(--purple)) center/cover no-repeat fixed, url(Images/school13.jpg) center/cover no-repeat fixed;
            padding: 5rem 10%;
        }

        .album-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .album-header {
            background: linear-gradient(90deg, #ffffff46, #3d85f8a1, #fd4ba793, #ffffff50);
            color: var(--black);
            padding: 2rem;
            text-align: center;
            border-radius: 15px;
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
            background: var(--white);
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
            border-color: var(--lyellow);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .album-thumb:hover img {
            transform: scale(1.1);
        }

        .album-thumb.active {
            border-color: var(--blue);
            box-shadow: 0 4px 12px rgba(26, 60, 122, 0.3);
        }

        .album-right {
            background: rgba(255, 255, 255, 0.716);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            color: var(--text-dark);
            max-height: 400px; /* keep right panel height bounded */
            overflow-y: auto;   /* enable vertical scrolling when content overflows */
            height: auto;
        }

        .album-right h3 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--pink);
            border-bottom: 3px solid var(--blue);
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
            border-bottom: 1px solid var(--lpink);
            line-height: 1.6;
        }

        .album-right li:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .album-right li strong {
            color: var(--blue);
        }

        .album-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .album-cta {
            background-color: var(--blue);
            color: var(--white);
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
            background-color: var(--dblue);
        }

        /* Responsive: remove the fixed max-height on narrow screens so the panel flows naturally */
        @media (max-width: 780px) {
            .album-right { max-height: none; overflow: visible; }
            .album-body { grid-template-columns: 1fr; }
        }

        /* Responsive navigation for Event.php — keep the same gradient color */
        @media (max-width: 992px) {
            .header .nav {
                flex-direction: column;
                align-items: center;
                height: auto;
                padding: 8px 10px;
            }
            .header .nav.container-inner { /* optional if class used below, won't break if not present */
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
                color: var(--black); /* keep color consistent */
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
	
    <div class="welcome" id="top">Welcome to Glorious God's Family Christian School
         <button onclick="cancel()">&#10540</button>
	</div>

	<!-------------------header_start--------------------->
    <header class="header">
   	    <!-- add id and container class to nav for consistent behavior and sticky toggle -->
   	    <div class="nav container-inner" id="navTop">
   	  	    <div class="logo logo-wrapper">
                <img src="Images/g2flogo.png"/>
   	  	    </div>
             
   	  	    <div class="menu">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="Event.php" class="active">Events</a></li>
                    <li><a href="program.php">Programs </a></li>
                    <li><a href="how.php">How it works</a></li>
                    
                </ul>
   	  	    </div>
   	    </div>
    </header>
</head>
<body>
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
                        <button class="album-thumb" data-src="Images/school18.jpg" aria-label="Open image 4" role="listitem">
                           <a href="buwanwika.php"><img src="Images/school18_thumb.jpg" onerror="this.src='Images/school18.jpg'" alt="Event 4"></a>
                        </button>
                        <button class="album-thumb active" data-src="Images/fieldtrip.jpg" aria-label="Open image 1" role="listitem">
                            <a href="fieldtrip.php"><img src="Images/fieldtrip.jpg" onerror="this.src='Images/fieldtrip.jpg'" alt="Event 1"></a>
                        </button>
                        <button class="album-thumb" data-src="Images/foundation day.jpg" aria-label="Open image 2" role="listitem">
                            <a href="foundationday.php"><img src="Images/foundation day.jpg" onerror="this.src='Images/foundation day.jpg'" alt="Event 2"></a>
                        </button>
                        <button class="album-thumb" data-src="Images/yearend.jpg" aria-label="Open image 3" role="listitem">
                            <img src="Images/yearend.jpg" onerror="this.src='Images/yearend.jpg'" alt="Event 3">
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
                        <li><strong>Buwan ng Wika (August 2017)</strong> — Celebrating Buwan ng wika.</li>
                        <li><strong>Field Trip (February 2019)</strong> — Students participated in a hands-on educational tour to explore nature and history firsthand.</li>
                        <li><strong>Foundation Day (March 2020)</strong> — Promoting teamwork and healthy lifestyles through athletic competitions and wellness activities.</li>
                        <li><strong>Year-end Party (December 2022)</strong> — Celebrated student achievements with games,  food, and awards recognizing.</li>
                        <li><strong>Film Showing(Dec 2024)</strong> — Memorable Film Showing</li>
                        <li><strong>8th moving up and commencement Exercise</strong> - celebrating student's achievements, recognized successful completion completion, and formally marked the transistion to their next academic level,</li>
                    </ul>
                   
                </aside>
            </div>
        </div>
    </section>
    <script type="text/javascript">
        function cancel() {
            const topEl = document.getElementById("top");
            if (topEl) {
                topEl.style.marginTop = '-50px';
            }
        }

        window.addEventListener('scroll', function() {
            // JS bug fix: use the element id 'navTop' and a properly scoped var
            var navTopEl = document.getElementById('navTop');
            if (navTopEl) {
                navTopEl.classList.toggle("sticky", window.scrollY > 250);
            }
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
        })();
    </script>

</body>
</html>