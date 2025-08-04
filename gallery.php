<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Gallery Slider</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .carousel {
      scroll-snap-type: x mandatory;
      overflow-x: auto;
      display: flex;
      scroll-behavior: smooth;
    }

    .carousel-item {
      scroll-snap-align: start;
      flex: none;
      width: 100%;
    }

    .carousel::-webkit-scrollbar {
      display: none;
    }

    /* Animasi muncul */
    @keyframes fadeInSlide {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .fade-in-left {
      animation: fadeInSlide 1s ease-out forwards;
    }
  </style>
</head>
<body class="bg-white text-black">

  <!-- Our Gallery Title as Image -->
  <div class="flex justify-start px-8 my-6">
    <img src="pp.png" alt="Our Gallery" class="w-80 h-auto fade-in-left">
  </div>

  <!-- Carousel container -->
  <div class="relative w-full overflow-hidden">
    <div class="carousel w-full" id="galleryCarousel">

<!-- Slide 1 -->
<div class="carousel-item grid grid-cols-3 gap-4 px-4" id="slide1">
  <img src="ft1.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
  <img src="ft2.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
  <img src="ft3.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
  <img src="ft4.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
  <img src="ft5.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
  <img src="ft6.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
</div>


      <!-- Slide 2 -->
      <div class="carousel-item grid grid-cols-3 gap-4 px-4" id="slide2">
        <img src="ft7.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
        <img src="ft8.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
        <img src="ft9.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
        <img src="ft10.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
        <img src="ft11.png" class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
        <img src="ft12.png"class="w-full rounded-lg transition duration-300 transform hover:scale-105 hover:shadow-lg hover:-translate-y-1">
      </div>
    </div>

    <!-- Slide Controls -->
    <div class="absolute bottom-4 right-4 flex space-x-2">
      <!-- Tombol Kiri -->
      <a href="#slide1" class="w-10 h-10 bg-lime-400 rounded-md flex items-center justify-center shadow-md hover:bg-lime-500 transform hover:scale-105 transition duration-200">
        <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-lime-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </div>
      </a>

      <!-- Tombol Kanan -->
      <a href="#slide2" class="w-10 h-10 bg-lime-400 rounded-md flex items-center justify-center shadow-md hover:bg-lime-500 transform hover:scale-105 transition duration-200">
        <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-lime-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </a>
    </div>
  </div>
</body>
</html>
