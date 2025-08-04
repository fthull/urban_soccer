<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Urban Soccer Field</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Saira&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca&display=swap" rel="stylesheet" />

  <!-- AOS CSS -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />

  <style>
    body {
      font-family: "Montserrat", sans-serif;
    }
    .background-image {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: -10;
    }
    .text-shadow {
      text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
    }
  </style>
</head>
<body>
  <div class="min-h-screen flex items-center px-6 py-12 relative">
    <img alt="Background" class="background-image" src="bg.png" />
    
    <div class="max-w-7xl w-full flex flex-col md:flex-row items-center md:items-start gap-10 md:gap-20">
      
<!-- Text Section -->
<div class="text-white max-w-xl pl-4 md:pl-12">
  <h1 class="text-[50.56px] leading-[80px] font-normal mb-6 text-shadow"
      style="font-family: 'Saira', sans-serif; color: #b3d600;"
      data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
    Urban Soccer Field, <br>
    <span class="text-white">Manahan</span>
  </h1>

  <p class="text-[28.88px] leading-[36px] font-normal text-white mb-6 text-shadow"
     style="font-family: 'Lexend Deca', sans-serif;"
     data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000">
    Urban Soccer Field’s mission is to provide a quality mini soccer with
    FIFA standards for every football-loving community. We unite the
    concepts of sports and entertainment into one.
  </p>

  <p class="text-[28.88px] leading-[36px] font-normal text-white text-shadow"
     style="font-family: 'Lexend Deca', sans-serif;"
     data-aos="fade-up" data-aos-delay="500" data-aos-duration="1000">
    Fields manufactured with next generation synthetic grass materials and
    fully equipped with restrooms, a bar, café.
  </p>
</div>

      <!-- Image Section -->
      <div class="w-full md:w-auto"
           data-aos="fade-left" data-aos-duration="1600">
        <img src="bg2.png" alt="Soccer image" class="w-full max-w-[430px] rounded-2xl shadow-lg" />
      </div>
    </div>
  </div>

  <!-- AOS JS -->
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    AOS.init();
  </script>
</body>
</html>
