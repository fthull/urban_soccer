<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URBAN SOCCER FIELD</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ96j7pwOQ+hD1tYkC5A14zI/t9f5q2VzJm3m94L1zGj02t00wP1j5+9z" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="header sticky-top">
        <nav class="navbar navbar-expand-lg">
            <div class="container d-flex justify-content-between align-items-center py-3 px-5">
                <div class="logo d-flex align-items-center gap-3">
                    <img src="asset/logo.png" alt="Logo Urban Soccer Field" width="120">
                    <h2 class="text-white fs-4 mb-0 fw-bold d-none d-lg-block">URBAN SOCCER FIELD</h2>
                </div>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link fw-bold mx-2 active" aria-current="page" href="#">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold mx-2" href="#">Booking</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold mx-2" href="#">Layanan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold mx-2" href="#">Kontak</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="hero-section">
        <div class="banner-slideshow">
            <div class="slides">
                <div class="slide" style="background-image: url('asset/banner2.png');"></div>
                <div class="slide" style="background-image: url('asset/banner3.png');"></div>
                <div class="slide" style="background-image: url('asset/banner.png');"></div>
            </div>
        </div>
        
        <div class="container hero-content">
            <div class="row">
                <div class="col-lg-8 text-start">
                    <h1 class="display-3 fw-bold mb-3">BOOK A FIELD</h1>
                    <p class="fs-5 mb-4">Book a field and play with your teamates</p>
                    <a href="#" class="btn-book btn btn-lg fw-bold">Book Now</a>
                </div>
            </div>
        </div>
    </main>

    <section class="offer-fasilitas-section">
        
        <div class="offer-body">
           <div class="offer-header">
            <div class="d-flex flex-wrap justify-content-start column">
                <ul>
                    <li><i class="fa-solid fa-futbol"></i> Lapangan ukuran 55 x 22 m</li>
                    <li><i class="fa-solid fa-lightbulb"></i> Lampu penerangan</li>
                    <li><i class="fa-solid fa-leaf"></i> Rumput sintetis Fifa Standar</li>
                </ul>
            </div>
            <div class="d-flex flex-wrap justify-content-start column">
                <ul>
                    <li><i class="fa-solid fa-restroom"></i> Kamar Mandi</li>
                    <li><i class="fa-solid fa-mug-hot"></i> Cafe and Bar</li>
                    <li><i class="fa-solid fa-square-parking"></i> Parkir</li>
                </ul>
            </div>
            <div class="address-section">
                <i class="fa-solid fa-location-dot"></i>
                <p>Jl. Ahmad Yani No. 321</p>
                <p>Manahan</p>
            </div>
        </div>
        
        
            <div class="container-fluid">
                
                <h2 class="mb-5">Our Offer</h2>
                <div class="content-slider">
                    <div class="slider-wrapper" id="sliderWrapper">
                        <div class="slide-item">
                            <img src="asset/service-1.jpg" alt="Rent A Field">
                            <div class="slide-content">
                            </div>
                        </div>
                        <div class="slide-item">
                            <img src="asset/service-2.jpg" alt="Open Play">
                            <div class="slide-content">
                               
                            </div>
                        </div>
                        <div class="slide-item private">
                            <img src="asset/service-3.jpg" alt="Private Event">
                            <div class="slide-content">
                               
                            </div>
                        </div>
                        <div class="slide-item">
                            <img src="asset/service-1.jpg" alt="Rent A Field 2">
                            <div class="slide-content">
                               
                            </div>
                        </div>
                        <div class="slide-item">
                            <img src="asset/service-2.jpg" alt="Open Play 2">
                            <div class="slide-content">
                               
                            </div>
                        </div>
                        <div class="slide-item private">
                            <img src="asset/service-3.jpg" alt="Private Event 2">
                            <div class="slide-content">
                               
                            </div>
                        </div>
                        
                    </div>
                     <section id="map-section" class="container map-section py-4">
            <h5 class="fw-bold text-center">Lokasi Kami</h5>
            <div class="d-flex justify-content-center mt-3">
                <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d1977.6067146143778!2d110.80558!3d-7.551691!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e7a15fee81573e3%3A0xfd6efa3549d3537!2sUrban%20Soccer%20Field!5e0!3m2!1sid!2sus!4v1754034420497!5m2!1sid!2sus"
                    width="90%" height="380" style="border:0; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </section>
                </div>
            </div>
        </div>
       
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sliderWrapper = document.getElementById('sliderWrapper');
        const slideItems = document.querySelectorAll('.slide-item');
        const totalSlides = slideItems.length;
        let currentIndex = 0;
        
        function updateSlider() {
            const slideWidth = slideItems[0].offsetWidth + 20;
            sliderWrapper.style.transform = `translateX(${-currentIndex * slideWidth}px)`;
        }

        function slide() {
            currentIndex++;
            if (currentIndex > totalSlides - 3) {
                currentIndex = 0;
            }
            updateSlider();
        }
        
        window.addEventListener('resize', updateSlider);
        updateSlider();

        setInterval(slide, 4000);
    </script>
    
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>