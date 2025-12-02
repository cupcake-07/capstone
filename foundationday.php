<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foundation Day 2025 | Celebration Gallery</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* --- VARIABLES --- */
        :root {
            /* Neon Accents */
            --neon-blue: #2de2e6;
            --neon-pink: #f706cf;
            
            /* Glass Surface Colors - Slightly more transparent to show the background */
            --glass-bg: rgba(10, 15, 30, 0.6); 
            --glass-border: rgba(255, 255, 255, 0.15);
            
            /* Layout */
            --card-radius: 32px; 
            --grid-gap: 24px;   
        }

        /* --- GLOBAL RESET --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            color: #fff;
            min-height: 100vh;
            padding-bottom: 80px;

            /* --- BACKGROUND SETUP --- */
            /* 1. Base Dark Color */
            background-color: #0a0a12;
            
            /* 2. The Darkish Blue & Pink Gradient Mesh */
            background-image: 
                /* Subtle technical grid overlay */
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                
                /* Large Deep Blue Glow (Top Left) */
                radial-gradient(circle at 10% 20%, rgba(26, 42, 108, 0.8) 0%, transparent 60%),
                
                /* Large Deep Pink Glow (Bottom Right) */
                radial-gradient(circle at 90% 80%, rgba(178, 31, 102, 0.6) 0%, transparent 60%);
            
            background-attachment: fixed; /* Keeps background still while scrolling */
            background-size: 50px 50px, 50px 50px, 100% 100%, 100% 100%; /* Sizing for grid vs gradients */
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
            padding: 100px 20px 80px;
            position: relative;
            animation: fadeInDown 0.8s ease-out;
        }

        h1 {
            font-size: 4.5rem;
            font-weight: 900;
            letter-spacing: -2px;
            line-height: 1;
            margin-bottom: 20px;
            
            /* Slight gradient on the text itself to pop against the bg */
            background: linear-gradient(to bottom right, #ffffff, #dcdcdc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            
            /* Shadow to separate text from the colorful background */
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.5));
            animation: fadeInDown 0.8s ease-out 0.2s both;
        }

        p.subtitle {
            font-size: 1.25rem;
            color: #dbe4f0; /* Brighter text to read against colored bg */
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
            font-weight: 300;
            animation: fadeInDown 0.8s ease-out 0.4s both;
        }

        .highlight { 
            color: var();
            font-weight: 700;
            
        }

        /* --- BENTO GRID --- */
        .container {
            max-width: 1300px; 
            margin: 0 auto;
            padding: 0 30px;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: 280px; 
            gap: var(--grid-gap);
        }

        /* --- CARD STYLING --- */
        .card {
            border-radius: var(--card-radius);
            position: relative;
            overflow: hidden;
            
            /* Semi-transparent background so the blue/pink shines through */
            background: rgba(20, 20, 35, 0.3);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(5px); /* Subtle blur behind the whole card */
            
            transition: all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
            cursor: pointer;
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .card:nth-child(1) { animation-delay: 0.6s; }
        .card:nth-child(2) { animation-delay: 0.7s; }
        .card:nth-child(3) { animation-delay: 0.8s; }
        .card:nth-child(4) { animation-delay: 0.9s; }
        .card:nth-child(5) { animation-delay: 1s; }
        .card:nth-child(6) { animation-delay: 1.1s; }

        /* Hover: Lift & Glow */
        .card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.6);
        }

        /* Specific Glows */
        .card:nth-child(odd):hover { box-shadow: 0 20px 50px -10px rgba(45, 226, 230, 0.3); } /* Blue Glow */
        .card:nth-child(even):hover { box-shadow: 0 20px 50px -10px rgba(247, 6, 207, 0.3); } /* Pink Glow */

        /* Image */
        .card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.7s ease;
        }

        .card:hover img {
            transform: scale(1.1);
        }

        /* --- GLASS INFO PANE --- */
        .card-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            
            /* Frosted Glass Effect */
            background: linear-gradient(to top, rgba(10, 12, 20, 0.95), rgba(10, 12, 20, 0.7), transparent);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            
            border: none;
            border-top: 1px solid rgba(255,255,255,0.1);
            border-radius: 0; 
            padding: 12px 20px;
            max-height: 35%;
            
            transform: translateY(0);
        }

        /* Typography inside card */
        .card-tag {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .tag-blue { 
            color: var(--neon-blue); 
            border: 1px solid rgba(45, 226, 230, 0.3);
            background: linear-gradient(135deg, rgba(45, 226, 230, 0.1), rgba(45, 226, 230, 0.05));
        }
        .tag-pink { 
            color: var(--neon-pink); 
            border: 1px solid rgba(247, 6, 207, 0.3);
            background: linear-gradient(135deg, rgba(247, 6, 207, 0.1), rgba(247, 6, 207, 0.05));
        }

        .card h3 {
            font-size: 1.3rem;
            margin-bottom: 4px;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .card p {
            font-size: 0.9rem;
            color: #d1d5db;
            display: -webkit-box;
            -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* --- BACK BUTTON --- */
        .back-button {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(45, 226, 230, 0.1);
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
            color: #0a0a12;
            box-shadow: 0 0 20px rgba(45, 226, 230, 0.5);
        }

        /* --- SPANS --- */
        .col-2 { grid-column: span 2; }
        .row-2 { grid-row: span 2; }

        /* --- RESPONSIVE --- */
        @media (max-width: 1100px) {
            h1 { font-size: 3.5rem; }
            .bento-grid {
                grid-template-columns: repeat(2, 1fr);
                grid-auto-rows: 320px;
            }
        }

        @media (max-width: 650px) {
            header { padding: 80px 20px 40px; }
            h1 { font-size: 2.8rem; }
            .bento-grid {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            .card { height: 350px; }
        }
    </style>
</head>
<body>

    <header>
        <h1><span style="color:var()"></span> FOUNDATION DAY ALBUM</h1>
        <p class="subtitle">A legacy built on excellence. Highlights from the <span class="highlight">Golden Jubilee</span> Foundation celebration.</p>
    </header>

    <div class="container">
        <div class="bento-grid">

            <!-- 1. Large Feature -->
            <div class="card col-2 row-2">
                <img src="Images/foundation1.jpg" alt="Opening Parade">
                <div class="card-info">
                    <span class="card-tag tag-blue">Grand Opening</span>
                    <h3>Opening Parade</h3>
                    <p>The symbolic and memorable event that marked the beginning of our foundation celebration with vibrant colors and enthusiasm.</p>
                </div>
            </div>

            <!-- 2. Tall Vertical -->
            <div class="card row-2">
                <img src="Images/foundation2.jpg" alt="Solo Performances">
                <div class="card-info">
                    <span class="card-tag tag-blue">Arts & Culture</span>
                    <h3>Preparations for events</h3>
                    <p>Students prepare for events and performances.</p>
                </div>
            </div>

            <!-- 3. Standard -->
            <div class="card">
                <img src="Images/foundation3.jpg" alt="Alumni Homecoming">
                <div class="card-info">
                    <span class="card-tag tag-blue">Community</span>
                    <h3>Alumni Homecoming</h3>
                    <p>Welcoming back our beloved alumnies.</p>
                </div>
            </div>

            <!-- 4. Standard -->
            <div class="card">
                <img src="Images/foundation4.jpg" alt="Tech Exhibit">
                <div class="card-info">
                    <span class="card-tag tag-blue">Innovation</span>
                    <h3>Teamwork</h3>
                    <p>Showing teamwork and student innovation and technical skills.</p>
                </div>
            </div>

            <!-- 5. Wide Landscape -->
            <div class="card col-2">
                <img src="Images/foundation7.jpg" alt="Championship Game">
                <div class="card-info">
                    <span class="card-tag tag-blue">Athletics</span>
                    <h3> Rewarding and Celebrating</h3>
                    <p>Rewards and celebrations for our students.</p>
                </div>
            </div>

            <!-- 6. Wide Landscape -->
            <div class="card col-2">
                <img src="Images/foundation6.jpg" alt="Closing Ceremony">
                <div class="card-info">
                    <span class="card-tag tag-blue">Finale</span>
                    <h3>Memorable Finale</h3>
                    <p>Closing the ceremony with a magnificent event completion and celebration.</p>
                </div>
            </div>

        </div>
    </div>

    <a href="javascript:history.back()" class="back-button">← Back</a>

</body>
</html>