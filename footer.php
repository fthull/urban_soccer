<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Urban Soccer Field Footer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <style>
      body {
        font-family: "Poppins", sans-serif;
      }

      :root {
        --usf-green: rgb(179, 214, 0);
      }

      .slider-nav a {
        width: 36px;
        height: 36px;
        background-color: var(--usf-green);
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        transition: background-color 0.2s;
      }

      .slider-nav a:hover {
        background-color: #c0db00;
      }

      .slider-nav a i {
        color: white;
        font-size: 1rem;
      }

      input:focus {
        color: var(--usf-green);
      }

      .hover-highlight li:hover,
      .hover-highlight p:hover {
        color: var(--usf-green);
        cursor: pointer;
      }

      .social-icon {
        font-size: 1.75rem;
      }
    </style>
  </head>

  <body class="bg-black text-white">
    <footer class="relative overflow-hidden">
      <img
        alt="Dark geometric pattern background"
        aria-hidden="true"
        class="absolute inset-0 w-full h-full object-cover opacity-70"
        src="bg.png"
      />

      <!-- Konten Footer -->
      <div class="relative max-w-7xl mx-auto px-6 py-16 flex flex-col items-center md:flex-row md:justify-between md:items-start gap-12 md:gap-0">
        <div class="flex flex-col items-center md:items-center max-w-md md:max-w-none text-center">
          <!-- Logo lebih besar dan tengah -->
          <img
            alt="USF Urban Soccer Field logo"
            class="w-64 md:w-72 mx-auto"
            src="footer.png"
          />
          <form class="mt-6 w-full md:w-72" onsubmit="event.preventDefault()">
            <label class="sr-only" for="email">Your Email Here</label>
            <div class="relative">
              <input
                class="w-full rounded px-4 py-3 pr-12 text-gray-300 bg-transparent border border-[rgb(179,214,0)] placeholder:text-gray-500 placeholder:font-semibold focus:outline-none focus:ring-1 focus:ring-[rgb(179,214,0)]"
                id="email"
                placeholder="Your Email Here"
                required
                type="email"
              />
              <button aria-label="Submit email" class="absolute top-1/2 right-3 -translate-y-1/2 text-white text-lg" type="submit">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </form>
        </div>

        <div class="flex flex-col md:flex-row md:gap-24 text-lg">
          <div>
            <h3 class="text-[rgb(179,214,0)] font-extrabold text-xl mb-4">Site</h3>
            <ul class="space-y-2 font-normal hover-highlight">
              <li>Booking</li>
              <li>About</li>
              <li>Gallery</li>
              <li>Our Offer</li>
              <li>Become a Partner</li>
            </ul>
          </div>

          <div>
            <h3 class="text-[rgb(179,214,0)] font-extrabold text-xl mb-4">Contact</h3>
            <address class="not-italic space-y-3 font-normal hover-highlight">
              <p>Jl. Ahmad Yani No. 321 Manahan, Surakarta</p>
              <p>info@urbansoccerfield.id</p>
              <p>+62 851 5656 5198</p>
            </address>
            <ul class="flex space-x-6 mt-4 text-white">
              <li>
                <a aria-label="YouTube" class="hover:text-[rgb(179,214,0)] social-icon" href="https://www.youtube.com/@urbAnsoccerfield">
                  <i class="fab fa-youtube"></i>
                </a>
              </li>
              <li>
                <a aria-label="Instagram" class="hover:text-[rgb(179,214,0)] social-icon" href="https://www.instagram.com/urbansoccerfield/">
                  <i class="fab fa-instagram"></i>
                </a>
              </li>
              <li>
<a aria-label="WhatsApp" 
   class="hover:text-[rgb(179,214,0)]" 
   href="https://wa.me/6285156565198?text=Halo%20Urban%20Soccer%20Field%2C%20saya%20mau%20booking%20lapangan." 
   target="_blank" rel="noopener noreferrer">
  <i class="fab fa-whatsapp social-icon"></i>
</a>

              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="text-center text-gray-300 text-sm py-4 border-t border-gray-800 font-normal">
        ©2023 By
        <a class="text-[rgb(179,214,0)] hover:underline" href="#">Look Creative</a>
        Made With <span class="text-red-600">❤️</span>
        <a class="text-[rgb(179,214,0)] hover:underline" href="#">Asiifdev.com</a>
      </div>
    </footer>
  </body>
</html>
