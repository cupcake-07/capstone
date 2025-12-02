<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graduation Album | Blue & Pink Theme</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* --- VARIABLES --- */
        :root {
            /* The specific gradient from your image */
            --gradient-overlay: linear-gradient(90deg, rgba(30, 64, 175, 0.85) 0%, rgba(190, 24, 93, 0.85) 100%);
            --accent-blue: #1e40af;
            --accent-pink: #be185d;
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            --text-dark: #1f2937;
            --text-light: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-dark);
            overflow-x: hidden; /* Prevent horizontal scroll from animations */
        }

        /* --- HERO SECTION (Like your picture) --- */
        .hero {
            position: relative;
            height: 85vh;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            
            background-image: url('Images/graduation1.jpg');
            background-size: cover;
            background-position: center;
            
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
            margin-bottom: 60px;
            z-index: 10;

            animation: fadeInDown 0.8s ease-out;
        }

        /* The Blue/Pink Overlay */
        .hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--gradient-overlay);
            z-index: -1;
        }

        .hero h1 {
            font-size: 5rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -2px;
            margin-bottom: 20px;
            text-shadow: 0 4px 10px rgba(0,0,0,0.3);

            animation: fadeInDown 0.8s ease-out 0.2s both;
        }

        .hero p {
            font-size: 1.5rem;
            font-weight: 400;
            opacity: 0.9;
            margin-bottom: 30px;

            animation: fadeInDown 0.8s ease-out 0.4s both;
        }

        /* --- MAIN CONTENT CONTAINER --- */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
            position: relative;
            z-index: 20;
        }

        /* --- ABOUT CARD (The white box with pink border) --- */
        .intro-card {
            background: white;
            border-radius: 12px;
            padding: 50px 60px;
            margin: -40px auto 100px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            
            border-left: 8px solid var(--accent-pink);
            
            text-align: center;
            max-width: 900px;

            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .intro-card h2 {
            color: var(--accent-pink);
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 800;
        }

        .intro-card p {
            color: var(--text-light);
            font-size: 1.1rem;
            line-height: 1.8;
            margin: 0;
        }

        /* --- BENTO GRID LAYOUT --- */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: 280px;
            gap: 28px;
            padding-bottom: 120px;

            animation: fadeInUp 0.8s ease-out 0.8s both;
        }

        .card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            transition: all 0.4s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            cursor: pointer;

            animation: fadeInUp 0.6s ease-out backwards;
        }

        /* Hover Effect */
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .card:hover img {
            transform: scale(1.1);
        }

        /* --- SPECIAL BENTO ITEMS --- */
        
        /* Layout Spans */
        .wide { grid-column: span 2; }
        .tall { grid-row: span 2; }
        .big  { grid-column: span 2; grid-row: span 2; }

        /* Text Overlay Cards */
        .card-content {
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .card.blue {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
        }

        .card.blue h3 { color: var(--accent-blue); }

        .card.pink {
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            border: 1px solid #fbcfe8;
        }
        
        .card.pink h3 { color: var(--accent-pink); }

        .card h3 {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .year-tag {
            font-size: 5rem;
            font-weight: 900;
            color: rgba(0,0,0,0.05);
            position: absolute;
            bottom: -10px;
            right: 10px;
        }

        /* --- BACK BUTTON --- */
        .back-button {
            position: relative;
            bottom: auto;
            left: auto;
            transform: none;
            background-color: rgba(30, 64, 175, 0.1);
            border: 1px solid var(--accent-blue);
            color: var(--accent-blue);
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
            background-color: var(--accent-blue);
            color: white;
            box-shadow: 0 0 20px rgba(30, 64, 175, 0.5);
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

        /* --- RESPONSIVE DESIGN --- */
        @media (max-width: 1024px) {
            .hero h1 { font-size: 3.5rem; }
            .intro-card {
                padding: 40px 50px;
                margin: -30px auto 80px;
            }
            .bento-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
                padding-bottom: 100px;
            }
            .big { grid-column: span 2; grid-row: span 1; }
        }

        @media (max-width: 600px) {
            .hero { height: 60vh; clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%); margin-bottom: 40px; }
            .hero h1 { font-size: 2.5rem; margin-bottom: 15px; }
            .hero p { font-size: 1rem; padding: 0 20px; margin-bottom: 20px; }
            
            .intro-card {
                margin: -20px 0 60px 0;
                padding: 30px 25px;
                border-radius: 8px;
            }

            .intro-card h2 { font-size: 2rem; margin-bottom: 15px; }

            .bento-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                padding-bottom: 80px;
            }
            
            .wide, .tall, .big { grid-column: span 1; grid-row: span 1; }
            .tall { min-height: 400px; }
        }
    </style>
</head>
<body>

    <!-- HERO SECTION -->
    <header class="hero">
        <h1>Glorious God's Family Christian School Graduates</h1>
        <p>The Future Begins Today. Wikang Filipino, Wikang Mapagpalaya.</p>
    </header>

    <div class="container">
        
        <!-- INTRO CARD (Similar to your screenshot's bottom card) -->
        <section class="intro-card">
            <h2>About the Journey</h2>
            <p>
                Welcome to our digital memory lane. This album celebrates the hard work, 
                friendships, and unforgettable moments of our graduating class. 
                Scroll down to view the gallery.
            </p>
        </section>

        <!-- BENTO GRID GALLERY -->
        <div class="bento-grid">
            
            <!-- Large Hero Image -->
            <div class="card big">
                <img src="Images/graduation2.jpg" alt="Group Graduation">
            </div>

            <!-- Pink Text Block -->
            <div class="card pink">
                <div class="card-content">
                    <h3>Dream<br>Big.</h3>
                    <p style="color: #be185d;">Keep reaching for the stars.</p>
                </div>
                <div class="year-tag">25</div>
            </div>

            <!-- Standard Image -->
            <div class="card">
                <img src="Images/graduation3.jpg" alt="Ceremony">
            </div>

            <!-- Tall Portrait Image -->
            <div class="card tall">
                <img src="Images/graduation4.jpg" alt="Portrait">
            </div>

            <!-- Blue Text Block -->
            <div class="card blue">
                <div class="card-content">
                    <h3>Memories<br>Forever.</h3>
                    <p style="color: #1e40af;">Friends that became family.</p>
                </div>
            </div>

            <!-- Wide Image -->
            <div class="card wide">
                <img src="Images/graduation5.jpg" alt="Diploma">
            </div>

            <!-- Standard Image -->
            <div class="card">
                <img src="Images/graduation6.jpg" alt="Flowers">
            </div>

            <!-- Standard Image -->
            <div class="card">
                <img src="Images/graduation7.jpg" alt="Party">
            </div>

        </div>
    </div>

    <div style="text-align: center;">
        <a href="javascript:history.back()" class="back-button">← Back</a>
    </div>

</body>
</html>