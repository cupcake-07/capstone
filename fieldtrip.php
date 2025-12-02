<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Trip Gallery | Blue & Pink Theme</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        /* --- THEME VARIABLES --- */
        :root {
            /* Palette */
            --bg-color: #0b0d17;       /* Very dark navy */
            --card-bg: #151a30;        /* Slightly lighter navy */
            --text-primary: #ffffff;
            --text-secondary: #aebcd9; /* Light blue-grey */
            
            /* Accents */
            --neon-blue: #00f2ff;
            --neon-pink: #ff007f;
            --gradient-text: linear-gradient(135deg, var(--neon-blue), var(--neon-pink));
            
            /* Layout */
            --gap: 20px;
            --radius: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif; /* Changed to a trendier font */
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(255, 0, 127, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(0, 242, 255, 0.1) 0%, transparent 40%);
            color: var(--text-primary);
            line-height: 1.6;
            padding-bottom: 50px;
            min-height: 100vh;
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

        /* --- HEADER --- */
        header {
            text-align: center;
            padding: 80px 20px;
            max-width: 900px;
            margin: 0 auto;
            animation: fadeInDown 0.8s ease-out;
        }

        h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: -1px;
            
            /* The Blue-Pink Gradient Text */
            background: var(--gradient-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            
            /* A subtle glow behind the text */
            text-shadow: 0px 0px 30px rgba(255, 0, 127, 0.3);
            animation: fadeInDown 0.8s ease-out 0.2s both;
        }

        p.subtitle {
            color: var(--text-secondary);
            font-size: 1.2rem;
            font-weight: 300;
            border-bottom: 2px solid var(--neon-pink);
            display: inline-block;
            padding-bottom: 5px;
            animation: fadeInDown 0.8s ease-out 0.4s both;
        }

        /* --- BENTO GRID LAYOUT --- */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: 250px; 
            gap: var(--gap);
        }

        /* --- CARD STYLING --- */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            position: relative;
            overflow: hidden;
            
            /* Subtle blue border initially */
            border: 1px solid rgba(0, 242, 255, 0.1);
            
            transition: all 0.4s ease;
            cursor: pointer;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .card:nth-child(1) { animation-delay: 0.6s; }
        .card:nth-child(2) { animation-delay: 0.7s; }
        .card:nth-child(3) { animation-delay: 0.8s; }
        .card:nth-child(4) { animation-delay: 0.9s; }
        .card:nth-child(5) { animation-delay: 1s; }
        .card:nth-child(6) { animation-delay: 1.1s; }

        .card:hover {
            transform: translateY(-8px);
            /* Pink Glow on Hover */
            box-shadow: 0 15px 40px rgba(255, 0, 127, 0.4);
            border-color: var(--neon-pink);
            z-index: 2;
        }

        /* Image Styling */
        .card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: block;
            opacity: 0.9; /* Slight dim to make text pop */
        }

        .card:hover img {
            transform: scale(1.1);
            opacity: 1;
        }

        /* Gradient Overlay for Text */
        .card-content {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 24px;
            
            /* Gradient matches the theme: Dark Blue to Transparent */
            background: linear-gradient(to top, rgba(11, 13, 23, 0.95), transparent);
            
            transform: translateY(0); /* Always visible now, cleaner look */
            max-height: 50%;
        }

        .card-title {
            font-weight: 800;
            font-size: 1.2rem;
            color: white;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Highlight title in blue/pink on hover */
        .card:hover .card-title {
            color: var(--neon-blue);
            text-shadow: 0 0 10px rgba(0, 242, 255, 0.5);
        }

        .card-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 400;
            line-height: 1.4;
        }

        /* --- BENTO SPANS --- */
        .col-span-2 { grid-column: span 2; }
        .row-span-2 { grid-row: span 2; }

        .back-button {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 242, 255, 0.1);
            border: 1px solid var(--neon-blue);
            color: var(--neon-blue);
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            z-index: 100;
        }

        .back-button:hover {
            background-color: var(--neon-blue);
            color: var(--bg-color);
            box-shadow: 0 0 20px rgba(0, 242, 255, 0.5);
        }

        /* --- RESPONSIVE BREAKPOINTS --- */
        @media (max-width: 900px) {
            h1 { font-size: 2.5rem; }
            .bento-grid {
                grid-template-columns: repeat(2, 1fr);
                grid-auto-rows: 300px;
            }
        }

        @media (max-width: 600px) {
            h1 { font-size: 2rem; }
            .bento-grid {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            .card { height: 320px; }
        }
    </style>
</head>
<body>

    <header>
        <h1>EDUCATIONAL FIELDTRIP AND ADVENTURES</h1>
        <p class="subtitle">Collection of photos from our fieldtrip</p>
    </header>

    <div class="container">
        <div class="bento-grid">
            
            <!-- Card 1: Large (Blue Emphasis) -->
            <div class="card col-span-2 row-span-2">
                <img src="Images/fieldtrip1.jpg" alt="Group Photo">
                <div class="card-content">
                    <div class="card-title">Giga foods</div>
                    <div class="card-desc">Giant food fun for the students.</div>
                </div>
            </div>

            <!-- Card 2: Tall (Pink Emphasis) -->
            <div class="card row-span-2">
                <img src="Images/fieldtrip2.jpg" alt="Dino Exhibit">
                <div class="card-content">
                    <div class="card-title">Fun time</div>
                    <div class="card-desc">Making your imagination come true</div>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="card">
                <img src="Images/fieldtrip3.jpg" alt="Lights">
                <div class="card-content">
                    <div class="card-title">More adventures</div>
                    <div class="card-desc">Learning and having fun</div>
                </div>
            </div>

            <!-- Card 4 -->
            <div class="card">
                <img src="Images/fieldtrip4.jpg" alt="Events">
                <div class="card-content">
                    <div class="card-title">Rides and Events</div>
                    <div class="card-desc">The main source of fun</div>
                </div>
            </div>

            <!-- Card 5: Wide -->
            <div class="card col-span-2">
                <img src="Images/fieldtrip5.jpg" alt="Nature Walk">
                <div class="card-content">
                    <div class="card-title">Botanical Gardens</div>
                    <div class="card-desc"> taking a detour to the gardens.</div>
                </div>
            </div>

            <!-- Card 6: Wide -->
            <div class="card col-span-2">
                <img src="Images/fieldtrip6.jpg" alt="Space Exhibit">
                <div class="card-content">
                    <div class="card-title">Learnings</div>
                    <div class="card-desc">Sighting about dinosaurs and more learnings</div>
                </div>
            </div>

        </div>
    </div>

    <a href="javascript:history.back()" class="back-button">← Back</a>

</body>
</html>