<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lincoln Elementary Film Fest</title>
    <!-- Importing a rounded, friendly font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        /* --- 1. THEME SETTINGS --- */
        :root {
            /* Friendly Blue & Pink */
            --color-blue: #4facfe;
            --color-pink: #f093fb;
            --color-dark: #1a1a2e; /* Soft dark blue background */
            --color-card: #252540;
            --color-white: #ffffff;
            
            --border-radius: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--color-dark);
            color: var(--color-white);
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        /* --- ANIMATIONS --- */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

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

        /* --- 2. HERO HEADER (Replaces Navbar + Hero Card) --- */
        .hero-header {
            width: 100%;
            height: 75vh; /* Takes up 75% of the screen height */
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            /* Background Image set here */
            background: url('Images/film1.jpg') no-repeat center center/cover;
            border-bottom: 5px solid var(--color-pink);
            animation: fadeInDown 0.8s ease-out;
        }

        /* Dark Overlay to make text readable */
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(26, 26, 46, 0.3), rgba(62 2 73 / 95%));
        }

        .hero-content {
            position: relative;
            z-index: 2;
            padding: 20px;
            max-width: 800px;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        /* School Branding (Moved inside Hero) */
        .school-brand {
            display: inline-block;
            margin-bottom: 15px;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            padding: 8px 20px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            animation: fadeInDown 0.8s ease-out 0.3s both;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 15px;
            text-shadow: 0 4px 10px rgba(255 248 248 / 50%);
            /* Gradient Text */
            background: linear-gradient(to right, var(--color-blue), var(--color-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeInDown 0.8s ease-out 0.4s both;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: #ffffff;
            margin-bottom: 30px;
            font-weight: 400;
            animation: fadeInDown 0.8s ease-out 0.5s both;
        }

        /* --- 3. BENTO GRID SECTION --- */
        .album-section {
            padding: 60px 5% 100px;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .section-header p {
            color: #aeb2d6;
            font-size: 1.1rem;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: 220px;
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .album-card {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            background-color: var(--color-card);
            border: 4px solid var(--color-white); /* Photo border style */
            transition: transform 0.3s, z-index 0.3s, box-shadow 0.3s;
            cursor: pointer;
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .album-card:hover {
            transform: scale(1.05) rotate(1deg);
            z-index: 10;
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            border-color: var(--color-blue);
        }

        .album-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-label {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--color-dark);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* --- BACK BUTTON --- */
        .back-button {
            position: relative;
            bottom: auto;
            left: auto;
            transform: none;
            background-color: rgba(79, 172, 254, 0.1);
            border: 1px solid var(--color-blue);
            color: var(--color-blue);
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            z-index: 100;
            margin-top: 40px;
        }

        .back-button:hover {
            background-color: var(--color-blue);
            color: var(--color-dark);
            box-shadow: 0 0 20px rgba(79, 172, 254, 0.5);
        }

        /* --- BENTO SIZES --- */
        .wide { grid-column: span 2; }
        .tall { grid-row: span 2; }
        .big  { grid-column: span 2; grid-row: span 2; }

        /* --- 4. RESPONSIVE --- */
        @media (max-width: 900px) {
            .hero-header { height: 60vh; }
            .hero-title { font-size: 3rem; }
            .bento-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .hero-header { height: 50vh; }
            .hero-title { font-size: 2.2rem; }
            .hero-subtitle { font-size: 1rem; }
            .bento-grid {
                grid-template-columns: 1fr;
                grid-auto-rows: 250px;
            }
            .wide, .tall, .big {
                grid-column: span 1;
                grid-row: span 1;
            }
        }
    </style>
</head>
<body>

    <!-- HERO HEADER (Full Width Background) -->
    <header class="hero-header">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <span class="school-brand">Glorious God's Family Christian School</span>
            <h1 class="hero-title">The Spring<br>Film Festival</h1>
            <p class="hero-subtitle">The School's memorable Film Showing</p>
        </div>
    </header>

    <!-- Bento Grid Pictures -->
    <section class="album-section">
        <div class="section-header">
            <h2>The Scrapbook</h2>
            <p>Memories from our little directors</p>
        </div>

        <div class="bento-grid">
            
            <!-- 1. Big Feature (2x2) -->
            <div class="album-card big">
                <img src="Images/film2.jpg" alt="Kids filming">
                
            </div>

            <!-- 2. Tall (1x2) -->
            <div class="album-card tall">
                <img src="Images/film3.jpg" alt="Popcorn">
               
            </div>

            <!-- 3. Standard -->
            <div class="album-card">
                <img src="Images/film4.jpg" alt="Audience">
               
            </div>

            <!-- 4. Standard -->
            <div class="album-card">
                <img src="Images/film5.jpg" alt="Clapperboard">
              
            </div>

            <!-- 5. Wide (2x1) -->
            <div class="album-card wide">
                <img src="Images/film6.jpg" alt="Group Photo">
               
            </div>

            <!-- 6. Standard -->
            <div class="album-card">
                <img src="Images/film7.jpg" alt="Movie Projector">
                
            </div>

            <!-- 7. Standard -->
            <div class="album-card">
                <img src="Images/film8.jpg" alt="Friends">
               
            </div>

        </div>
    </section>

    <div style="text-align: center;">
        <a href="javascript:history.back()" class="back-button">← Back</a>
    </div>

</body>
</html>