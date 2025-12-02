<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buwan ng Wika Celebration</title>
    <style>
        /* --- CSS VARIABLES & RESET --- */
        :root {
            --blue: #2563eb;
            --light-blue: #60a5fa;
            --pink: #db2777;
            --light-pink: #f472b6;
            --bg-color: #f8fafc;
            --text-dark: #1e293b;
            --text-light: #ffffff;
            --gradient-main: linear-gradient(135deg, var(--blue), var(--pink));
            --gradient-hover: linear-gradient(135deg, var(--pink), var(--blue));
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* --- HERO SECTION --- */
        .hero {
            height: 80vh;
            background: linear-gradient(135deg, rgba(64 102 183 / 70%), rgba(99 0 44 / 92%)),
                        url('Images/buwanwika1.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            color: var(--text-light);
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
            animation: slideInLeft 0.8s ease-out;
        }

        .hero-content h1 {
            font-size: 5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.2);
            margin-bottom: 10px;
        }

        .hero-content p {
            font-size: 1.5rem;
            font-weight: 300;
        }

        /* --- ABOUT SECTION --- */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        .about {
            text-align: center;
            margin-bottom: 60px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
            padding: 60px 40px;
            border-radius: 15px;
            border-left: 5px solid var(--pink);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .about h2 {
            font-size: 2.5rem;
            background: -webkit-linear-gradient(45deg, var(--blue), var(--pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 30px;
            font-weight: 700;
        }

        .about p {
            font-size: 1.1rem;
            line-height: 1.9;
            color: #475569;
            max-width: 850px;
            margin: 0 auto;
        }

        .about p strong {
            color: var(--blue);
            font-weight: 600;
        }

        /* --- BENTO GRID GALLERY --- */
        .album-section h2 {
            font-size: 2rem;
            margin-bottom: 30px;
            color: var(--text-dark);
            border-left: 5px solid var(--pink);
            padding-left: 15px;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* 4 Columns */
            grid-auto-rows: 200px; /* Base height for rows */
            gap: 15px;
        }

        .bento-item {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            background-color: #eee;
            animation: fadeInUp 0.6s ease-out;
        }

        .bento-item:hover {
            transform: translateY(-5px);
        }

        .bento-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .bento-item:hover img {
            transform: scale(1.1);
        }

        /* Overlay Text on Images */
        .overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(to top, rgba(110, 3, 92, 0.8), transparent);
            padding: 20px;
            color: white;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .bento-item:hover .overlay {
            opacity: 1;
        }

        /* --- BENTO SIZING CLASSES --- */
        /* These classes create the "Bento" mosaic look */
        
        .span-2 {
            grid-column: span 2;
        }
        
        .row-2 {
            grid-row: span 2;
        }
        
        .span-3 {
            grid-column: span 3;
        }

        /* --- FOOTER --- */
        footer {
            background: var(--text-dark);
            color: white;
            text-align: center;
            padding: 40px;
            margin-top: 50px;
        }

        footer p span {
            color: var(--pink);
            font-weight: bold;
        }

        /* --- ANIMATIONS --- */
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

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .bento-item:nth-child(1) { animation-delay: 0.6s; }
        .bento-item:nth-child(2) { animation-delay: 0.7s; }
        .bento-item:nth-child(3) { animation-delay: 0.8s; }
        .bento-item:nth-child(4) { animation-delay: 0.9s; }
        .bento-item:nth-child(5) { animation-delay: 1s; }
        .bento-item:nth-child(6) { animation-delay: 1.1s; }
        .bento-item:nth-child(7) { animation-delay: 1.2s; }
        .bento-item:nth-child(8) { animation-delay: 1.3s; }

        /* --- GO BACK BUTTON --- */
        .btn-back {
            display: inline-block;
            margin-right: 20px;
            padding: 10px 20px;
            background: var(--pink);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s ease, transform 0.2s ease;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-back:hover {
            background: var(--blue);
            transform: translateX(-5px);
        }

        /* --- RESPONSIVE DESIGN --- */
        @media (max-width: 768px) {
            .hero-content h1 { font-size: 3rem; }
            
            .bento-grid {
                grid-template-columns: 1fr; /* Stack on mobile */
                grid-auto-rows: 250px;
            }

            .span-2, .span-3, .row-2 {
                grid-column: span 1;
                grid-row: span 1;
            }
        }
    </style>
</head>
<body>

    <!-- Hero Section -->
    <header class="hero">
        <div class="hero-content">
            <h1>Buwan ng Wika</h1>
            <p>Wikang Filipino, Wikang Mapagpalaya</p>
        </div>
    </header>

    <div class="container">
        <!-- Description Section -->
        <section class="about">
            <h2>About the Event</h2>
            <p>
                <strong>Buwan ng Wika</strong> (Language Month) is an annual celebration in the Philippines held every August, 
                commemorating the birth of national hero José Rizal on August 19th. This month-long observance is dedicated to 
                promoting and preserving the Filipino language as a vital instrument of national unity, cultural identity, and freedom.
                <br><br>
                The Filipino language, also known as Tagalog, serves as the foundation of our national identity and bridges 
                the diverse communities across the archipelago. Through Buwan ng Wika, we celebrate our rich linguistic heritage 
                and the power of words to unite us as one people.
                <br><br>
                <strong>This year's celebration features:</strong>
                <br>
                • <strong>Sayaw (Traditional Dances)</strong> - Showcasing the vibrant cultural movements from different regions
                <br>
                • <strong>Traditional Costumes</strong> - Wearing the iconic Baro't Saya for women and Barong Tagalog for men, 
                symbols of our cultural pride and heritage
                <br><br>
                Join us in this meaningful celebration of language, culture, and national pride!
            </p>
        </section>

        <!-- Bento Grid Album Section -->
        <section class="album-section">
            <h2>Event Highlights Album</h2>
            
            <div class="bento-grid">
                
                <!-- Large Feature Box (Top Left) -->
                <div class="bento-item span-2 row-2">
                    <img src="Images/buwanwika.jpg" alt="Traditional Dance">
                    <div class="overlay">
                        <h3>Traditional Dance</h3>
                        <p>Student Performance</p>
                    </div>
                </div>

                <!-- Small Box -->
                <div class="bento-item">
                    <img src="Images/buwanwika2.jpg" alt="Filipino Flag">
                    <div class="overlay"><h3>Pride</h3></div>
                </div>

                <!-- Small Box -->
                <div class="bento-item">
                    <img src="Images/buwanwika3.jpg" alt="Costumes">
                    <div class="overlay"><h3>Costumes</h3></div>
                </div>

                <!-- Medium Wide Box -->
                <div class="bento-item span-2">
                    <img src="Images/buwanwika4.jpg" alt="Filipino Food">
                    <div class="overlay"><h3>Folk Dances</h3></div>
                </div>

                <!-- Tall Box -->
                <div class="bento-item row-2">
                    <img src="Images/buwanwika6.jpg" alt="Decoration">
                    <div class="overlay"><h3>Awarding</h3></div>
                </div>

                <!-- Wide Box -->
                <div class="bento-item span-3">
                    <img src="Images/buwanwika5.jpg" alt="Group Photo">
                    <div class="overlay"><h3>Appreciation</h3><p>The whole school community.</p></div>
                </div>

                <!-- Small Box -->
                <div class="bento-item">
                    <img src="Images/buwanwika7.jpg" alt="Smiles">
                    <div class="overlay"><h3>Program</h3></div>
                </div>
                 <!-- Small Box -->
                 <div class="bento-item">
                    <img src="Images/buwanwika8.jpg" alt="Singing">
                    <div class="overlay"><h3>Traditional costumes</h3></div>
                </div>

            </div>
        </section>
    </div>

    <footer>
        <a href="javascript:history.back()" class="btn-back">← Go Back</a>
        <p>Glorious God's Family Christian School &copy; 2025</p>
    </footer>

</body>
</html>