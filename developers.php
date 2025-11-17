<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>Developer Team - MediQ</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <?php include_once ("includes/css-links-inc.php"); ?>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #1abc9c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: var(--shadow);
        }

        .logo span {
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .team-section {
            padding: 80px 0 40px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
        }

        .section-title h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .section-title h1:after {
            content: '';
            position: absolute;
            width: 70px;
            height: 4px;
            background: var(--accent-color);
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .section-title p {
            color: #7f8c8d;
            max-width: 600px;
            margin: 0 auto;
        }

        .team-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            padding: 25px 20px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .team-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.15);
        }

        .team-card img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--light-color);
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .team-card:hover img {
            border-color: var(--accent-color);
        }

        .team-card h5 {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .team-card p {
            color: #7f8c8d;
            margin-bottom: 20px;
            flex-grow: 1;
            font-size: 0.95rem;
        }

        .team-icons {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: auto;
        }

        .team-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light-color);
            color: var(--primary-color);
            transition: var(--transition);
            text-decoration: none;
        }

        .team-icons a:hover {
            background-color: var(--secondary-color);
            color: white;
            transform: scale(1.1);
        }

        .team-icons a i {
            font-size: 1.2rem;
        }

        .dev-footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-align: center;
            padding: 25px 0;
            margin-top: 50px;
        }

        .dev-footer p {
            margin-bottom: 5px;
        }

        .dev-footer a {
            color: var(--accent-color);
            font-weight: 600;
            text-decoration: none;
        }

        .dev-footer a:hover {
            text-decoration: underline;
        }

        .back-to-top {
            background-color: var(--accent-color);
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99;
            transition: var(--transition);
            text-decoration: none;
        }

        .back-to-top:hover {
            background-color: var(--secondary-color);
            transform: translateY(-3px);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .team-card {
                margin-bottom: 20px;
            }
            
            .section-title h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>

    <!-- ======= Header ======= -->
    <header id="header" class="header fixed-top d-flex align-items-center">
        <div class="d-flex align-items-center justify-content-between">
            <a href="pages-home.php" class="logo d-flex align-items-center">
                <img src="assets\images\logos\favicon.png" width="35px">
                <span class="d-none d-lg-block">Medi-Q</span>
            </a>
        </div><!-- End Logo -->
    </header><!-- End Header -->

    <!-- Team Section -->
    <section class="team-section container">
        <div class="section-title">
            <h1>Meet Our Development Team</h1>
            <p>Passionate developers working together to build exceptional healthcare solutions</p>
        </div>
        <div class="row g-4 justify-content-center">
            <!-- Team Member 1-->
            <div class="col-md-4 col-lg-3">
                <div class="team-card">
                    <img src="assets\images\Developers\malitha3.jpg" alt="Malitha Tishmal">
                    <h5>Malitha Tishmal</h5>
                    <p><b>Full Stack Developer</b>
                        <br>Quality Assurance
                        <br>System Administrator</p>
                    <div class="team-icons">
                        <a href="https://malithatishamal.42web.io/" target="_blank" title="Website"><i class="bi bi-globe"></i></a>
                        <a href="https://github.com/malitha-tishamal" target="_blank" title="GitHub"><i class="bi bi-github"></i></a>
                        <a href="https://www.linkedin.com/in/malitha-tishamal" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="https://www.facebook.com/malitha.tishamal" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="mailto:malithatishamal@gmail.com" title="Email"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-lg-3">
                <div class="team-card">
                    <img src="assets\images\Developers\684c0f0cbbca3-1.jpg" alt="Malith Sandeepa">
                    <h5>Malith Sandeepa</h5>
                    <p><b>Frontend Developer</b>
                       <br>Quality Assurance
                        <br>System Administrator</p>
                    <div class="team-icons">
                        <a href="#" target="_blank" title="Website"><i class="bi bi-globe"></i></a>
                        <a href="https://github.com/KVMSANDEEPA" target="_blank" title="GitHub"><i class="bi bi-github"></i></a>
                        <a href="www.linkedin.com/in/malith-sandeepa" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="https://www.facebook.com/profile.php?id=100071177107363" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="mailto:malithsandeepa1081@gmail.com" title="Email"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-lg-3">
                <div class="team-card">
                    <img src="assets\images\Developers\tharidu.jpg" alt="Tharindu Sampath">
                    <h5>Tharindu Sampath</h5>
                    <p><b>Frontend Developer</b>
                        <br>System Administrator</p>
                    <div class="team-icons">
                        <a href="#" target="_blank" title="Website"><i class="bi bi-globe"></i></a>
                        <a href="https://github.com/VgTharindu" target="_blank" title="GitHub"><i class="bi bi-github"></i></a>
                        <a href="https://www.linkedin.com/in/vg-tharindu-0b0158272?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="https://www.facebook.com/share/1Dd22cM9oN/" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="mailto:vgtharindu165@gmail.com" title="Email"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
            </div>

            <!-- Team Member 4 -->
            <div class="col-md-4 col-lg-3">
                <div class="team-card">
                    <img src="assets\images\Developers\nishara.jpg" alt="Nishara de Silva">
                    <h5>Nishara de Silva</h5>
                    <p><b>Frontend Developer</b>
                        <br>System Administrator</p>
                    <div class="team-icons">
                        <a href="#" target="_blank" title="Website"><i class="bi bi-globe"></i></a>
                        <a href="https://github.com/Tharu003" target="_blank" title="GitHub"><i class="bi bi-github"></i></a>
                        <a href="https://www.linkedin.com/in/nishara-de-silva-992409329/" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="#" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="mailto:tharushinishara2003@gmail.com" title="Email"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-lg-3">
                <div class="team-card">
                    <img src="assets\images\Developers\684ec27f3fb39-img.jpg" alt="Matheesha Nihari">
                    <h5>Matheesha Nihari</h5>
                    <p><b>Frontend Developer</b>
                        <br>System Administrator</p>
                    <div class="team-icons">
                        <a href="#" target="_blank" title="Website"><i class="bi bi-globe"></i></a>
                        <a href="https://github.com/Matheesha-Nihari" target="_blank" title="GitHub"><i class="bi bi-github"></i></a>
                        <a href="https://www.linkedin.com/in/matheesha-nihari-4a6913350/" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="https://www.facebook.com/share/12KZGoMHc3H/?mibextid=LQQJ4d" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="mailto:matheenihari13@gmail.com" title="Email"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
            </div>

            <!-- Commented out team members -->
            <!--
            <div class="col-md-4 col-lg-3">
                <div class="team-card">
                    <img src="assets\images\Developers\amandi.jpg" alt="Amandi Kaushalya">
                    <h5>Amandi Kaushalya</h5>
                    <p><b>Frontend Developer</b>
                        <br>System Administrator</p>
                    <div class="team-icons">
                        <a href="#" target="_blank" title="Website"><i class="bi bi-globe"></i></a>
                        <a href="https://github.com/Amandi-Kaushalya-Dewmini" target="_blank" title="GitHub"><i class="bi bi-github"></i></a>
                        <a href="www.linkedin.com/in/amandi-kaushalya-dewmini-4059b5352" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="https://www.facebook.com/profile.php?id=100090649864805&mibextid=ZbWKwL" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="mailto:dewmikaushalya112@gmail.com" title="Email"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
            </div>
            -->
        </div>
    </section>

    <!-- Footer -->
    <div class="dev-footer">
        <p>&copy; Copyright <strong><span>MediQ</span></strong> All Rights Reserved</p>
        <p>Developed by <a href="developers.php">Devlk Team</a></p>
    </div>

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

</body>

</html>