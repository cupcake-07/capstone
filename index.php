<!DOCTYPE html>
<html lang="en">
<head>
	<title>G2F</title>
	<link rel="stylesheet" href="style.css">
	<link rel="icon" href="Images/future.png">
	<link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
	<meta charset="utf-8">
	<meta name="description" content="This is a top online job website. where we provide online and offline jobs.....">
	<meta name="keywords" content="Web , Android , Database , PHP">
	<meta name="author" content="Md S. Alam">
	<meta name="viewport" content="width=device-width,initial-scale=1.0">
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Responsive Design Fixes */
        * {
            box-sizing: border-box;
        }
        
        img {
            max-width: 100%;
            height: auto;
        }

        body, html {
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* Ensure containers don't overflow */
        .container-inner, .header, .about-section, .how, .footer {
            width: 100%;
            max-width: 100vw;
        }

        /* Welcome banner (top) - make it part of the document flow and responsive */
        .welcome {
            position: relative; /* allow absolute close button */
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            width: 100%;
            box-sizing: border-box;
            padding: 8px 12px;
            text-align: center;
            font-weight: 600;
            color: #4b4949ff;
            z-index: 9999;
            /* keep your existing background — do not override if style.css sets a gradient */
        }
        
        /* Text wrapper for the welcome content. Limits width, controls wrapping and line-height */
        .welcome-text {
            display: inline-block;
            max-width: calc(100% - 64px); /* leave ~64px for the close button */
            width: auto;
            text-align: center;
            line-height: 1.25;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
            hyphens: auto;
            padding: 0 6px;
            box-sizing: border-box;
            margin: 0;
        }
        
        /* Close button: absolutely positioned so it doesn't push the text into awkward lines */
        .welcome .close-btn {
            position: absolute;
            right: 8px;
            top: 8px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.05rem;
            cursor: pointer;
            padding: 6px;
            line-height: 1;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        /* ensure the text and button do not grow/shrink awkwardly */
        .welcome > .welcome-text, .welcome > button {
            flex: 0 1 auto;
        }

        /* Close button in welcome: small, anchored to the right inside banner */
        .welcome > button {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.05rem;
            cursor: pointer;
            padding: 6px 8px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 8px;
            border-radius: 4px;
        }

        /* Ensure the header is not overlapped if any previous CSS used fixed positioning */
        .header.page-hero {
            margin-top: 0 !important; /* header should not be overlapped by welcome */
        }

        /* Fix for the case header/menu has fixed position: add top spacing equal to banner height */
        /* only when the header is fixed in your style.css (if it is) */
        @media (max-width: 992px) {
            .header.page-hero.fixed, .header.page-hero.sticky {
                padding-top: 56px; /* adjust if your header is fixed to avoid overlap */
                box-sizing: border-box;
            }
        }

        /* Tighten banner on smaller screens to avoid taking too much vertical space */
        @media screen and (max-width: 768px) {
            .welcome {
                padding: 8px 10px;
                font-size: 0.94rem;
                gap: 8px;
            }
            .welcome > button { font-size: 1rem; padding: 6px; }
        }

        @media screen and (max-width: 480px) {
            .welcome {
                padding: 6px 8px !important;
                font-size: 0.9rem;
            }
            .welcome > button { font-size: 0.95rem; padding: 5px 6px; }
        }

        @media screen and (max-width: 992px) {
            .nav.container-inner {
                flex-direction: column;
                height: auto;
                padding: 10px;
                align-items: center;
            }
            
            .menu ul {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                padding: 0;
                margin-top: 10px;
            }
            
            .menu ul li {
                margin: 5px 10px;
            }

            .sub.abs-photo {
                display: flex;
                flex-direction: column;
                height: auto;
            }

            .sub.abs-photo .para, 
            .sub.abs-photo .photo {
                width: 100% !important;
                position: relative;
                top: auto;
                left: auto;
                right: auto;
                padding: 20px;
            }
            
            .how .box {
                width: 46%;
                margin: 2%;
                display: inline-block;
                vertical-align: top;
            }

            /* Add footer centering across smaller screens */
            .footer {
                text-align: center !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
            }

            .footer h1 { text-align: center !important; }

            .footer .section .content {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 18px !important; /* gives a little breathing room */
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .footer .section .content ul {
                width: 100% !important;
                max-width: 360px !important; /* prevents lists from stretching across the screen on narrow devices */
                margin: 0 auto !important;
                padding: 0 8px !important;
                list-style: none !important;
                box-sizing: border-box !important;
                text-align: center !important;
            }

            .footer .section .content ul h4 { text-align: center !important; }
            .footer .section .content ul li { text-align: center !important; display: block !important; }
            .footer .section .content ul li a { text-align: center !important; display: inline-block !important; }

            .footer .link {
                display: flex !important;
                flex-direction: row !important; /* icons in one row */
                justify-content: center !important;
                align-items: center !important;
                gap: 10px !important;
                width: 100% !important;
                margin: 8px 0 !important;
            }
            .footer .link h4 { text-align: center !important; width: 100% !important; margin: 0; }
            .footer .link a { display: inline-flex !important; justify-content: center !important; align-items: center !important; }

            .bottom {
                text-align: center !important;
                padding: 12px !important;
                margin: 0 auto !important;
            }
        }

        /* Make sure smaller breakpoints keep center alignment */
        @media screen and (max-width: 768px) {
            /* ...existing code... */

            /* enforce footer centering here as well */
            .footer, .footer h1, .footer .section .content, .footer .section .content ul, .footer .link, .bottom {
                text-align: center !important;
            }

            .footer .section .content {
                gap: 12px !important;
                align-items: center !important;
            }

            .footer .section .content ul {
                max-width: 420px !important;
                padding: 10px 8px !important;
            }

            .footer .link {
                flex-direction: row !important;
                justify-content: center !important;
            }
        }

        @media screen and (max-width: 480px) {
            /* ...existing code... */

            /* tighter footer spacing on very small phones */
            .footer .section .content ul {
                max-width: 320px !important;
                padding-left: 6px !important;
                padding-right: 6px !important;
                margin: 6px auto !important;
            }

            .footer .link {
                gap: 8px !important;
                flex-wrap: wrap !important;
            }

            .footer .link a {
                width: 36px !important;
                height: 36px !important;
                display: inline-flex !important;
                justify-content: center !important;
                align-items: center !important;
            }

            .bottom {
                padding: 8px 6px !important;
                font-size: 0.8rem !important;
            }
        }

        /* Extra small devices */
        @media screen and (max-width: 480px) {
            .header .content {
                padding: 25px 10px;
            }

            .header .content h1 {
                font-size: 1.3rem;
                margin-bottom: 8px;
            }

            .header .content p {
                font-size: 0.9rem;
                margin: 8px auto;
                line-height: 1.4;
            }

            .header .content .border {
                padding: 10px;
                margin: 10px auto;
            }

            .how h1 {
                font-size: 1.5rem;
            }

            .cta button {
                width: 90%;
                font-size: 0.95rem;
            }

            .about-section .para {
                padding: 12px;
            }

            .about-section .para p {
                margin-bottom: 10px;
            }
        }

        /* Additional mobile / small device overrides to remove side gutters/extra spacing */
        @media screen and (max-width: 768px) {
            /* Ensure top-level containers fill viewport and remove extra left/right gutters */
            .page-hero, .container-inner, .content.container-inner, .about-section, .sub.abs-photo {
                width: 100%;
                max-width: 100%;
                padding-left: 12px !important;
                padding-right: 12px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                box-sizing: border-box;
            }

            /* Hide decorative pseudo elements that might be adding vertical stripes */
            .page-hero::before, .page-hero::after,
            .content::before, .content::after,
            .sub::before, .sub::after,
            .marq::before, .marq::after,
            .box::before, .box::after {
                display: none !important;
                content: none !important;
                border-radius:50%;
            }

            /* Reduce or remove heavy left/right borders that create side whitespace */
            .content .border {
                border-left-width: 0 !important; /* remove vertical decorative line */
                border-right-width: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
            }

            /* Make sure absolutely positioned photos/content are stacked and not cut off */
            .sub.abs-photo .photo, .sub.abs-photo .para {
                position: relative !important;
                left: auto !important;
                right: auto !important;
                top: auto !important;
                width: 100% !important;
                padding: 0 6px !important;
            }

            /* Force image to not overflow */
            .photo img, .logo img, .welcome_big img, .scale {
                max-width: 100% !important;
                height: auto !important;
                display: block;
                margin: 0 auto;
            }

            /* Prevent horizontal overflow caused by marquee/boxes */
            .marq, .marq .box {
                width: 100% !important;
                max-width: 100% !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            /* Center and wrap text; avoid text overflow */
            h1, h2, h3, p, li {
                text-align: center !important;
                white-space: normal !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
            }

            /* Fine tune header spacing so text doesn't appear outside bounds */
            .header.page-hero {
                padding-left: 12px !important;
                padding-right: 12px !important;
            }

            /* Make CTA area fit better on small screens */
            .cta {
                padding: 0;
                margin: 12px auto;
            }
            .cta button {
                width: 90%;
                padding: 10px 12px;
            }

            /* Modal adjustments, ensure no overflow from input groups */
            #inquire_modal .box, .container .box {
                width: 95% !important;
                max-width: 95% !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }

        } /* end max-width: 768px */

        /* Extra small devices - keep tight spacing */
        @media screen and (max-width: 480px) {
            /* Reduce padding and margins further to reduce vertical whitespace */
            .page-hero, .content.container-inner {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
            .header .content h1 {
                font-size: 1.25rem !important;
            }
            .header .content p, .about-section .para p {
                margin: 6px 0 !important;
                padding: 0 6px !important;
            }
            .cta button {
                width: 95% !important;
            }
        }

        @media screen and (max-width: 768px) {
            /* Compact nav for tablets / mobiles */
            .nav.container-inner {
                padding: 6px 12px !important;
                gap: 6px !important;
                align-items: center !important;
            }

            /* Make logo and menu smaller */
            .logo img {
                max-width: 110px !important;
                height: auto !important;
                margin: 0 !important;
            }

            .nav .logo {
                padding: 0 !important;
                margin-right: 8px !important;
            }

            /* Tighten menu item spacing */
            .menu {
                width: auto !important;
            }
            .menu ul {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 6px !important;
                justify-content: center !important;
                margin-top: 6px !important;
                padding: 0 !important;
            }
            .menu ul li {
                margin: 4px 6px !important;
            }
            .menu ul li a {
                font-size: 0.95rem !important;
                padding: 6px 8px !important;
                letter-spacing: 0.6px !important;
            }

            /* Reduce login item size */
            .menu ul li.login-nav a {
                font-size: 0.92rem !important;
                padding: 6px 10px !important;
            }

            /* Reduce header top padding to keep nav compact */
            .header.page-hero {
                padding-top: 8px !important;
            }

            /* If the menu is still too wide, stack items vertically but tightly */
            .menu.stack-on-mobile ul { /* add class to force vertical, apply if needed */ 
                flex-direction: column !important;
                gap: 6px !important;
                align-items: center !important;
            }
        }

        @media screen and (max-width: 480px) {
            /* Extra-small devices: further compress nav and fonts */
            .logo img {
                max-width: 92px !important;
            }

            .nav.container-inner {
                padding: 6px 8px !important;
            }

            /* Make menu vertical with smaller spacing for small phones */
            .menu ul {
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
                padding: 0 !important;
                margin-top: 8px !important;
            }
            .menu ul li {
                margin: 4px 0 !important;
            }
            .menu ul li a {
                font-size: 0.9rem !important;
                padding: 6px 10px !important;
            }

            /* Slightly reduce CTA width */
            .cta button {
                width: 88% !important;
                padding: 10px 12px !important;
            }
        }

        /* Small screens: make nav compact and avoid text cut-off */
    @media (max-width: 768px) {
      /* compact nav container */
      .nav.container-inner {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 8px !important;
        padding: 8px 10px !important;
        width: 100% !important;
        box-sizing: border-box !important;
      }

      /* logo smaller */
      .logo.logo-wrapper {
        flex: 0 0 auto !important;
        padding: 0 !important;
        margin: 0 !important;
      }
      .logo.logo-wrapper img {
        max-width: 92px !important;
        height: auto !important;
        display: block !important;
      }

      /* menu uses remaining space and is centered */
      .menu {
        flex: 1 1 auto !important;
        display: flex !important;
        justify-content: center !important;
      }

      /* stack menu items vertically and ensure full width so text wraps */
      .menu ul {
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        list-style: none !important;
        align-items: center !important;
      }

      .menu ul li {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        text-align: center !important;
      }

      /* Turn anchors into full-row items to allow wrapping and prevent cut */
      .menu ul li a {
        display: inline-block !important;
        width: auto !important;
        padding: 8px 12px !important;
        font-size: 0.95rem !important;
        line-height: 1.2 !important;
        text-decoration: none !important;
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        text-align: center !important;
        letter-spacing: 0.6px !important;
      }

      /* If you have a login nav style, keep it smaller but consistent */
      .menu ul li.login-nav a {
        font-size: 0.92rem !important;
        padding: 8px 10px !important;
      }

      /* Remove negative margins / side gutters, ensure no overflow */
      .page-hero, .container-inner, .content.container-inner, .about-section {
        padding-left: 8px !important;
        padding-right: 8px !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }

      /* If menu becomes taller than viewport, allow scroll within menu */
      .menu ul {
        max-height: calc(100vh - 110px) !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
      }
    }

    /* Extra small phones: shrink font-size/padding a bit more */
    @media (max-width: 480px) {
      .nav.container-inner {
        padding: 6px 8px !important;
        gap: 6px !important;
      }
      .logo.logo-wrapper img { max-width: 80px !important; }
      .menu ul li a { font-size: 0.9rem !important; padding: 7px 10px !important; }
      .header .content { padding: 18px 10px !important; }
      .header .content h1 { font-size: 1.15rem !important; }
      .cta button { width: 92% !important; padding: 9px 12px !important; }
    }

    /* Ensure text never overflows horizontally */
    h1, h2, p, li, .menu ul li a {
      overflow-wrap: break-word !important;
      word-break: break-word !important;
      white-space: normal !important;
    }

    /* Ensure marquee boxes are perfectly circular and responsive */
:root {
  --marq-circle-size: clamp(64px, 11vw, 140px); /* adjust min/ideal/max circle sizes */
}

.marq {
  display: inline-flex;
  gap: 12px;
  align-items: center;
  /* Ensure marquee boxes don't overflow the marquee container */
  white-space: nowrap;
}

/* Target only the marq .box to not interfere with other .box rules */
.marq .box {
  --size: var(--marq-circle-size);
  width: var(--size);
  height: var(--size);
  aspect-ratio: 1/1; /* modern browsers */
  display: inline-flex;
  align-items: center;
  justify-content: center;
  position: relative; /* for the overlay */
  border-radius: 50%; /* circle shape */
  overflow: hidden; /* clip everything to circle */
  background-size: cover; /* preserve image fill */
  background-position: center center;
  background-repeat: no-repeat;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  flex: 0 0 auto; /* keep intrinsic width in horizontal flow */
}

/* If markup uses an <img> inside the .box, ensure it is rounded and fits */
.marq .box img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
  border-radius: 50%;
}

/* Center and style overlay text inside the circle */
.marq .box .over {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}
.marq .box .over h4,
.marq .box .over h5 {
  margin: 0;
  padding: 6px;
  font-size: 0.95rem;
  text-align: center;
  color: #fff;
  text-shadow: 0 1px 1px rgba(0,0,0,0.5);
  line-height: 1.1;
  pointer-events: none;
}

/* Preserve circle size on smaller devices (if you want smaller circles on small screens) */
@media screen and (max-width: 480px) {
  :root { --marq-circle-size: clamp(48px, 18vw, 84px); }
}

/* Accessibility: reduce visual blending of overlay text for poor contrast backgrounds */
.marq .box .over {
  background: rgba(0, 0, 0, 0.18); /* subtle overlay to improve text legibility */
  border-radius: 50%;
}

/* Force marquee boxes to stay circular even if other CSS tries to stretch them */
:root {
  --marq-circle-size: clamp(64px, 11vw, 140px); /* you can customize these values */
}

/* This selector is intentionally specific and placed after other rules to override them */
.about-section .marq, .about-section .marq .box {
  gap: 12px; /* keep the gap between circles */
  white-space: nowrap; /* ensure inline scrolling, do not wrap to second line */
  display: inline-flex; 
  align-items: center;
  overflow-x: auto; /* allow horizontal scrolling rather than stacking vertically */
  -webkit-overflow-scrolling: touch;
}

/* Enforce circular shape and prevent width:100% overrides */
.about-section .marq .box {
  --size: var(--marq-circle-size);
  width: var(--size) !important;
  height: var(--size) !important;
  min-width: var(--size) !important;
  min-height: var(--size) !important;
  max-width: var(--size) !important;
  max-height: var(--size) !important;
  aspect-ratio: 1 / 1 !important; /* modern browsers */
  display: inline-flex;
  align-items: center;
  justify-content: center;
  position: relative;
  border-radius: 50% !important;
  overflow: hidden !important;
  background-size: cover !important;
  background-position: center center !important;
  flex: 0 0 var(--size) !important; /* prevent flexbox from stretching */
  box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

/* Make sure <img> inside .box stays clipped and covers the container */
.about-section .marq .box img {
  width: 100% !important;
  height: 100% !important;
  display: block !important;
  object-fit: cover !important;
  border-radius: 50% !important;
}

/* Keep overlay and text centered (no stretching) */
.about-section .marq .box .over {
  background: rgba(0,0,0,0.18);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  position: absolute;
  inset: 0;
  padding: 4px;
  text-align: center;
}

/* Smaller circles on very small screens but still circular */
@media (max-width: 480px) {
  :root { --marq-circle-size: clamp(48px, 18vw, 84px); }
  .about-section .marq .box {
    --size: var(--marq-circle-size);
    width: var(--size) !important;
    height: var(--size) !important;
    min-width: var(--size) !important;
    min-height: var(--size) !important;
  }
}

/* ---------- Remove marquee scrollbars and background ---------- */
/* Hide default scrollbar tracks across browsers and ensure transparent background */
marquee,
.about-section marquee,
.about-section .marq {
  overflow: hidden !important;          /* prevent scrollbars from appearing */
  background: transparent !important;   /* remove any dark background */
  border: none !important;              /* remove any default border */
  width: 100%;
  display: block;
  -ms-overflow-style: none;             /* IE and Edge */
  scrollbar-width: none;                /* Firefox */
}

/* WebKit scrollbar rules – hide the scrollbar visually */
marquee::-webkit-scrollbar,
.about-section .marq::-webkit-scrollbar {
  height: 0 !important;
  width: 0 !important;
  display: none !important;
}

/* Ensure marquee child container doesn't create a visible track */
.about-section .marq {
  overflow: hidden !important;
  background: transparent !important;
  padding-bottom: 0;    /* remove any accidental padding that might reveal a track */
  line-height: 0;       /* remove extra inline spacing under inline-flex children */
}

/* Keep circles circular and avoid any overflow area showing */
.about-section .marq .box {
  /* box sizing already in place, make sure no box shadow or background causes a full-width stripe */
  background-clip: border-box;
  box-shadow: 0 2px 6px rgba(0,0,0,0.12); /* subtle shadow only on the circles */
}

/* If any global styles force width:100% on .marq or .marq .box (e.g. in media queries),
   the specific rules above (and !important on overflow/background) prevent a scroll-track from appearing. */
    </style>
</head>
<body>

	<div class="welcome" id="top" style="position:relative; display:flex; justify-content:center; align-items:center; flex-wrap:wrap; gap:6px; padding:8px 12px; text-align:center; box-sizing:border-box;">
        <span class="welcome-text" style="max-width:calc(100% - 64px);">Welcome to Glorious God's Family Christian School</span>
         <button class="close-btn" onclick="cancel()" aria-label="Close welcome banner" style="background:transparent; border:none; color:#fff; font-size:1.05rem; padding:6px; cursor:pointer;">&#10540</button>
	</div>

	<!-------------------header_start--------------------->

  <header class="header page-hero">
   	  <div class="nav container-inner">
   	  	  <div class="logo logo-wrapper">
              <img src="Images/g2flogo.png"/>
   	  	  </div>
   	  	  <div class="menu">
   	  	  	 <ul>
   	  	  	 	<li><a href="index.php">Home</a></li>
   	  	  	 	<li><a href="Event.php">Events</a></li>
   	  	  	 	<li><a href="program.php">Programs </a></li>
              <li><a href="how.php">How It Works</a></li>
   	  	  	    
   	  	  	 	
   	  	  	 	<li class="login-nav"><a href="login.php">Login</a></li>
   	  	  	 </ul>
   	  	  </div>
   	  </div>

   	  <div class="content container-inner">
   	  	<h1>Welcome to Glorious God's Family Christian School!
   	  		<br>Are you looking for new learning opportunities?</h1>
         <p class="border">Welcome to our elementary school community. We provide a nurturing, safe, and stimulating environment for children from Kindergarten through Grade 6. Our curriculum focuses on foundational literacy and numeracy, social‑emotional growth, creativity, and hands‑on learning—so each child can build confidence, character, and a love of learning.</p>
          <p class="slog"><em>"Nurturing hearts and minds — growing learners for tomorrow."</em></p>

          <div class="cta" role="group" aria-label="Call to actions">
            <button id="inquireBtn" class="gradient-btn" type="button" aria-controls="inquire_modal" aria-haspopup="dialog" aria-expanded="false">Inquire Now</button>
            
          </div>

   	  </div>

   </header>

  <!---------------header_end----------->

   <!--------------------About_us-start--------------------->
     <div class="about-section" id="about_us">
     	 

     <div class="sub abs-photo">

      <div class="para">
     		<p>Glorious God's Family Christian School is a welcoming elementary school serving families in our community. We provide a warm, caring environment where young learners are encouraged to explore, discover, and grow.</p>

                <p>Our teachers focus on building strong foundational skills in reading, writing, and math while also supporting social and emotional development through play, projects, and cooperative activities. We use age‑appropriate technology and hands‑on learning to spark curiosity.</p>

                <p>We partner closely with families to create a safe, inclusive place where children can build confidence, develop good character, and prepare for the next steps in their education.</p>
      </div>

      <div class="photo" aria-hidden="false">
        <img src="Images/studentslanding.png" alt="School students and campus" onerror="this.src='Images/fbs_logo.png'">
      </div>

   </div>

   

        <div class="marquee" aria-hidden="true" title="School activities">
          <div class="marq" id="marqTrack" aria-hidden="true">
            <div class="box" id="img1"><div class="over"><h4>Field Trip</h4></div></div>
            <div class="box" id="img2"><div class="over"><h4>Assessments</h4></div></div>
            <div class="box" id="img3"><div class="over"><h4>Student GSSG</h4></div></div>
            <div class="box" id="img4"><div class="over"><h5 style="color:azure; font-family:calibri;">Buwan ng Wika</h5></div></div>
            <div class="box" id="img5"><div class="over"><h4>Film Showing</h4></div></div>
            <div class="box" id="img6"><div class="over"><h4>Planting</h4></div></div>
            <div class="box" id="img7"><div class="over"><h4>Oath Taking</h4></div></div>
            <div class="box" id="img8"><div class="over"><h4>Christmas Party</h4></div></div>
            <div class="box" id="img9"><div class="over"><h4>Graduation Day</h4></div></div>
            <div class="box" id="img10"><div class="over"><h4>Nutrition Month</h4></div></div>
            <div class="box" id="img11"><div class="over"><h4>Play Time </h4></div></div>
            <div class="box" id="img12"><div class="over"><h4>Plant Activities</h4></div></div>
            <div class="box" id="img13"><div class="over"><h4>Thanks Giving Party</h4></div></div>
            <div class="box" id="img14"><div class="over"><h4>Contests</h4></div></div>
            <div class="box" id="img15"><div class="over"><h4>Happy Events</h4></div></div>
            <div class="box" id="img16"><div class="over"><h4>Year-end Party</h4></div></div>
          </div>
        </div>

     </div>
  <!--------------------About_us-end--------------------->

  <!----------------------career_start----------------->
    
  <!----------------------career_end----------------->

  <!------------skills_start---------------->
   
  <!------------skills_end---------------->

  <!------------form_start-------------------->
 
         </div>
       </div>
  </div>
  <!------------form_end-------------------->

  <!-------------How_it-works-start--------------->
   
  <!-------------How_it-works-end--------------->

  <!------------contact_us-start---------------->
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

                 <ul>
                    <h4>Office Hours</h4>
                    <li>Mon–Fri: 07:00 – 17:00</li>
                    <li>Sat: 10:00 – 12:00</li>
                    <li>Sun: Closed</li>
                 </ul>
              </div>
          </section>

             <div class="link" aria-hidden="false">
                <h4>Follow us</h4>
                <a href="https://www.facebook.com/GloriousgfcsiCluster2" target="_blank" rel="noopener"><i class="fa fa-facebook"></i></a>
                <a href="mailto:arnel.shin@gmail.com"><i class="fa fa-envelope"></i></a>
                <!-- apply teacher button (opens modal) -->
                
            </div>

          

        </footer>

       <div class="bottom"><i class="fa fa-copyright"></i>2025 Glorious God's Family Christian School || all rights reserved.</div>
   <!------------contact_us-end---------------->

   <!------------------ Welcome start------------------>

       <section class="welcome_big">
            <img src="Images/fbs_logo.png" class="scale" alt="Logo" onerror="this.src='Images/fbs_logo.png'"/>
       </section>

   <!------------------ Welcome end ------------------>

   <!------------javascript code start---------------->
   <script type="text/javascript">
  // Initialize behaviors
  document.addEventListener('DOMContentLoaded', function () {
    // Close the welcome banner
    function cancel() {
      const topEl = document.getElementById('top');
      if (topEl) topEl.style.display = 'none';
    }
    window.cancel = cancel;

    // Toggle sticky top arrow on scroll
    window.addEventListener("scroll", function () {
      var top = document.getElementById('navTop'); if (top) top.classList.toggle("sticky", window.scrollY > 250);
    });

    // application form "close" button from the original form_act
    var formCloseBtn = document.getElementById('close');
    if (formCloseBtn) {
      formCloseBtn.addEventListener("click", function () {
        var formAct = document.getElementById('form_act');
        if (formAct) formAct.classList.remove('show');
      });
    }

    // INQUIRE modal variables
    const inquireBtn = document.getElementById('inquireBtn');
    const inquireModal = document.getElementById('inquire_modal');
    const inquireClose = document.getElementById('inquireClose');
    const inquireForm = document.getElementById('inquireForm');
    const inquireStatus = document.getElementById('inquireStatus');
    let lastFocusedElement = null;

    // Helper
    function getFocusableElements(container) {
      if (!container) return [];
      return Array.prototype.slice.call(
        container.querySelectorAll('a[href], button:not([disabled]), textarea, input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')
      );
    }

    // Focus-trap for a modal: generic helper
    function trapFocus(container, e) {
      if (e.key !== 'Tab') return;
      const focusable = getFocusableElements(container);
      if (!focusable.length) return;
      const first = focusable[0]; const last = focusable[focusable.length - 1];
      if (e.shiftKey) { if (document.activeElement === first) { e.preventDefault(); last.focus(); } }
      else { if (document.activeElement === last) { e.preventDefault(); first.focus(); } }
    }

    // Open/Close inquire modal
    function openInquireModal() {
      if (!inquireModal) return;
      lastFocusedElement = document.activeElement;
      inquireModal.classList.add('show');
      inquireModal.setAttribute('aria-hidden', 'false');
      inquireModal.setAttribute('aria-modal', 'true');
      document.body.classList.add('no-scroll');
      if (inquireBtn) inquireBtn.setAttribute('aria-expanded', 'true');
      const focusable = getFocusableElements(inquireModal);
      if (focusable.length) focusable[0].focus();
      inquireModal.addEventListener('keydown', function (e) { trapFocus(inquireModal, e); });
    }
    function closeInquireModal() {
      if (!inquireModal) return;
      inquireModal.classList.remove('show');
      inquireModal.setAttribute('aria-hidden', 'true');
      inquireModal.removeAttribute('aria-modal');
      document.body.classList.remove('no-scroll');
      if (inquireStatus) { inquireStatus.textContent = ''; inquireStatus.classList.remove('error'); }
      if (inquireBtn) inquireBtn.setAttribute('aria-expanded', 'false');
      if (lastFocusedElement) try { lastFocusedElement.focus(); } catch (e) {}
    }
    if (inquireBtn) inquireBtn.addEventListener('click', openInquireModal);
    if (inquireClose) inquireClose.addEventListener('click', closeInquireModal);
    if (inquireModal) inquireModal.addEventListener('click', function (e) { if (e.target === inquireModal) closeInquireModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && inquireModal && inquireModal.classList.contains('show')) closeInquireModal(); });

    // Submit inquiry form
    if (inquireForm) {
      inquireForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const name = document.getElementById('inqName').value.trim();
        const email = document.getElementById('inqEmail').value.trim();
        const message = document.getElementById('inqMessage').value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email)) { if (inquireStatus) { inquireStatus.textContent = 'Please enter a valid email address.'; inquireStatus.classList.add('error'); } return; }
        if (!message) { if (inquireStatus) { inquireStatus.textContent = 'Please write your question.'; inquireStatus.classList.add('error'); } return; }
        const submitBtn = inquireForm.querySelector('input[type="submit"]'); if (submitBtn) submitBtn.disabled = true;
        fetch('submit_inquiry.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ name: name, email: email, message: message })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            inquireStatus.classList.remove('error'); inquireStatus.textContent = 'Thank you! Your inquiry has been submitted.';
            setTimeout(function () { closeInquireModal(); if (submitBtn) submitBtn.disabled = false; inquireForm.reset(); }, 600);
          } else {
            inquireStatus.classList.add('error'); inquireStatus.textContent = data.error || 'Submission failed. Try again later.'; if (submitBtn) submitBtn.disabled = false;
          }
        })
        .catch(err => { console.error('Error:', err); if (inquireStatus) { inquireStatus.classList.add('error'); inquireStatus.textContent = 'Submission failed.'; } if (submitBtn) submitBtn.disabled = false; });
      });
      inquireForm.addEventListener('reset', function () { if (inquireStatus) { inquireStatus.textContent = ''; inquireStatus.classList.remove('error'); } });
    }

    // Apply as Teacher modal handling (open from footer)
    const applyTeacherBtn = document.getElementById('applyTeacherBtn');
    const applyTeacherModal = document.getElementById('apply_teacher_modal');
    const applyTeacherClose = document.getElementById('applyTeacherClose');
    const applyTeacherForm = document.getElementById('applyTeacherForm');
    const applyTeacherStatus = document.getElementById('applyTeacherStatus');
    let lastFocusedElementTeacher = null;

    function openApplyTeacherModal() {
      if (!applyTeacherModal) return;
      lastFocusedElementTeacher = document.activeElement;
      applyTeacherModal.classList.add('show');
      applyTeacherModal.setAttribute('aria-hidden', 'false');
      applyTeacherModal.setAttribute('aria-modal', 'true');
      document.body.classList.add('no-scroll');
      if (applyTeacherBtn) applyTeacherBtn.setAttribute('aria-expanded', 'true');
      const focusable = getFocusableElements(applyTeacherModal);
      if (focusable.length) focusable[0].focus();
      applyTeacherModal.addEventListener('keydown', function (e) { trapFocus(applyTeacherModal, e); });
    }
    function closeApplyTeacherModal() {
      if (!applyTeacherModal) return;
      applyTeacherModal.classList.remove('show'); applyTeacherModal.setAttribute('aria-hidden', 'true'); applyTeacherModal.removeAttribute('aria-modal'); document.body.classList.remove('no-scroll');
      if (applyTeacherStatus) applyTeacherStatus.textContent = '';
      if (applyTeacherBtn) applyTeacherBtn.setAttribute('aria-expanded', 'false');
      if (lastFocusedElementTeacher) try { lastFocusedElementTeacher.focus(); } catch (e) {}
    }

    if (applyTeacherBtn) applyTeacherBtn.addEventListener('click', openApplyTeacherModal);
    if (applyTeacherClose) applyTeacherClose.addEventListener('click', closeApplyTeacherModal);
    if (applyTeacherModal) applyTeacherModal.addEventListener('click', function (e) { if (e.target === applyTeacherModal) closeApplyTeacherModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && applyTeacherModal && applyTeacherModal.classList.contains('show')) closeApplyTeacherModal(); });

    if (applyTeacherForm) {
      applyTeacherForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const name = document.getElementById('appName').value.trim();
        const email = document.getElementById('appEmail').value.trim();
        const qualifications = document.getElementById('appQualifications').value.trim();
        const experience = document.getElementById('appExperience').value.trim();
        const message = document.getElementById('appMessage').value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email)) { applyTeacherStatus.textContent = 'Please enter a valid email address.'; applyTeacherStatus.classList.add('error'); return; }
        if (!name) { applyTeacherStatus.textContent = 'Please enter your full name.'; applyTeacherStatus.classList.add('error'); return; }
        const submitBtnApply = applyTeacherForm.querySelector('input[type="submit"]'); if (submitBtnApply) submitBtnApply.disabled = true;
        // send via mailto as fallback to avoid forcing server-side implementation
        const adminEmailApply = (applyTeacherModal && applyTeacherModal.dataset && applyTeacherModal.dataset.adminEmail) ? applyTeacherModal.dataset.adminEmail : 'arnel.shin@gmail.com';
        const subject = encodeURIComponent('Teacher Application from ' + name);
        const body = encodeURIComponent('Name: ' + name + '\nEmail: ' + email + '\nQualifications: ' + qualifications + '\nExperience: ' + experience + '\nMessage:\n' + message);
        window.location.href = `mailto:${adminEmailApply}?subject=${subject}&body=${body}`;
        applyTeacherStatus.classList.remove('error'); applyTeacherStatus.textContent = 'Your mail client should open now. Thank you!';
        setTimeout(function () { closeApplyTeacherModal(); if (submitBtnApply) submitBtnApply.disabled = false; }, 400);
      });
      applyTeacherForm.addEventListener('reset', function () { applyTeacherStatus.textContent = ''; applyTeacherStatus.classList.remove('error'); });
    }

    // LOGIN modal variables removed - now using direct navigation

  }); // end DOMContentLoaded
  </script>

<!-- Simplified inquire modal structure -->
<div class="container inquire" id="inquire_modal" data-admin-email="arnel.shin@gmail.com" aria-hidden="true" role="dialog" aria-labelledby="inquireTitle" tabindex="-1">
  <div class="cancel" id="inquireClose" aria-label="Close inquire dialog">X</div>
  <div class="box" role="document">
    <div class="input-group">
      <h1 id="inquireTitle">INQUIRE NOW</h1>
      <form id="inquireForm" class="form" novalidate action="#" method="POST">
        <div class="infield input-field">
          <label for="inqName">Full Name</label>
          <input id="inqName" name="name" type="text" placeholder="Your name (optional)">
        </div>
        <div class="infield input-field">
          <label for="inqEmail">Email Address</label>
          <input id="inqEmail" name="email" type="email" placeholder="you@example.com" required>
        </div>
        <div class="infield input-field">
          <label for="inqMessage">Your Question</label>
          <textarea id="inqMessage" name="message" placeholder="Write your question here..." required></textarea>
        </div>
        <div class="input-field">
          <input type="reset" class="reset" value="Clear">
          <input type="submit" class="submit gradient-btn" value="Send Inquiry">
        </div>
        <div id="inquireStatus" aria-live="polite"></div>
      </form>
    </div>
  </div>
</div>



</body>
</html></div>