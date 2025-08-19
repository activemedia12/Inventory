<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>Coming Soon | Active Media Designs and Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(90deg,
                    rgba(176, 0, 176, 0.8) 0%,
                    rgba(0, 145, 255, 0.8) 70%,
                    rgba(255, 255, 0, 0.8) 100%);
            color: white;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            overflow: hidden;
        }

        .comingsoon {
            max-width: 800px;
            padding: 2rem;
            position: relative;
            z-index: 1;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(3px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .comingsoon h1 {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            background: linear-gradient(to right,
                    rgba(255, 255, 0, 1),
                    rgba(176, 0, 176, 1));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }

        .comingsoon p {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            color: #ffffff;
            font-weight: 500;
        }

        .countdown {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .countdown-item {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
            min-width: 100px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .countdown-item:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.1);
        }

        .countdown-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .countdown-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.7);
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .social-links a {
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .social-links a:hover {
            transform: translateY(-5px) scale(1.1);
            background: linear-gradient(45deg,
                    rgba(176, 0, 176, 0.8),
                    rgba(0, 145, 255, 0.8));
            box-shadow: 0 5px 15px rgba(0, 145, 255, 0.4);
        }

        .notify-btn {
            background: linear-gradient(45deg,
                    rgba(176, 0, 176, 0.8),
                    rgba(0, 145, 255, 0.8));
            border: none;
            color: white;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 5px 15px rgba(0, 145, 255, 0.4);
        }

        .notify-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(176, 0, 176, 0.6);
            background: linear-gradient(45deg,
                    rgba(176, 0, 176, 1),
                    rgba(255, 255, 0, 0.8));
        }

        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }

            100% {
                transform: translateY(-1000px) rotate(720deg);
            }
        }

        /* Responsive Media Queries */
        @media (max-width: 992px) {
            .comingsoon h1 {
                font-size: 3.5rem;
            }

            .comingsoon p {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 768px) {
            .comingsoon {
                padding: 1.5rem;
                width: 95%;
                scale: 0.9;
            }

            .comingsoon h1 {
                font-size: 2.8rem;
                margin-bottom: 1rem;
            }

            .comingsoon p {
                font-size: 1.2rem;
                margin-bottom: 1.5rem;
            }

            .countdown {
                gap: 1rem;
            }

            .countdown-item {
                padding: 1rem;
                min-width: 80px;
            }

            .countdown-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .comingsoon {
                scale: 0.8;
            }

            .comingsoon h1 {
                font-size: 2.2rem;
                letter-spacing: 2px;
            }

            .comingsoon p {
                font-size: 1rem;
            }

            .countdown {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .countdown-item {
                padding: 0.8rem;
                min-width: 70px;
                width: calc(50% - 1rem);
            }

            .countdown-number {
                font-size: 1.8rem;
            }

            .countdown-label {
                font-size: 0.8rem;
            }

            .notify-btn {
                padding: 0.8rem 1.5rem;
                font-size: 0.9rem;
            }

            .social-links a {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
        }

        @media (max-width: 400px) {
            .comingsoon {
                scale: 0.7;
            }

            .comingsoon h1 {
                font-size: 1.8rem;
            }

            .countdown-item {
                min-width: 60px;
                padding: 0.6rem;
            }

            .countdown-number {
                font-size: 1.5rem;
            }
        }

        .nav-menu {
            list-style: none;
            z-index: 1000;
            left: 0;
            margin-top: 20px;
        }

        .nav-menu li a {
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            background-color: var(--light-gray);
        }

        .nav-menu li a i {
            margin-right: 10px;
            color: var(--gray);
        }
    </style>
</head>

<body>
    <div class="floating-elements">
        <div class="floating-element" style="width: 100px; height: 100px; top: 20%; left: 10%; background: rgba(176, 0, 176, 0.1);"></div>
        <div class="floating-element" style="width: 150px; height: 150px; top: 60%; left: 70%; background: rgba(0, 145, 255, 0.1);"></div>
        <div class="floating-element" style="width: 80px; height: 80px; top: 80%; left: 30%; background: rgba(255, 255, 0, 0.1);"></div>
        <div class="floating-element" style="width: 120px; height: 120px; top: 30%; left: 80%; background: rgba(176, 0, 176, 0.1);"></div>
        <div class="floating-element" style="width: 60px; height: 60px; top: 70%; left: 20%; background: rgba(0, 145, 255, 0.1);"></div>
    </div>

    <div class="comingsoon">
        <h1>COMING SOON!</h1>
        <p>Active Media Designs and Printing</p>

        <div class="countdown">
            <div class="countdown-item">
                <div class="countdown-number" id="days">00</div>
                <div class="countdown-label">Days</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="hours">00</div>
                <div class="countdown-label">Hours</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="minutes">00</div>
                <div class="countdown-label">Minutes</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-number" id="seconds">00</div>
                <div class="countdown-label">Seconds</div>
            </div>
        </div>

        <p>We're creating something extraordinary for you!</p>

        <div class="social-links">
            <a href="https://www.facebook.com/profile.php?id=100063881538670" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="https://web.whatsapp.com/" aria-label="Whatsapp"><i class="fab fa-whatsapp"></i></a>
            <a href="https://www.viber.com/en/" aria-label="Viber"><i class="fab fa-viber"></i></a>
            <a href="https://www.linkedin.com/login" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
        </div>

        <div class="nav-menu">
            <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </div>
    </div>

    <script>
        // Set the date we're counting down to (change this to your launch date)
        const countDownDate = new Date(2025, 11, 1, 12, 0, 0).getTime();

        // Update the count down every 1 second
        const x = setInterval(function() {
            // Get today's date and time
            const now = new Date().getTime();

            // Find the distance between now and the count down date
            const distance = countDownDate - now;

            // Time calculations for days, hours, minutes and seconds
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Display the result
            document.getElementById("days").innerHTML = days.toString().padStart(2, '0');
            document.getElementById("hours").innerHTML = hours.toString().padStart(2, '0');
            document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, '0');
            document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, '0');

            // If the count down is finished, write some text
            if (distance < 0) {
                clearInterval(x);
                document.getElementById("days").innerHTML = "00";
                document.getElementById("hours").innerHTML = "00";
                document.getElementById("minutes").innerHTML = "00";
                document.getElementById("seconds").innerHTML = "00";
            }
        });
    </script>
</body>

</html>