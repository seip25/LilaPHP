<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | Page Not Found - LilaPHP</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .float-animation {
            animation: float 4s ease-in-out infinite;
        }
    </style>
</head>

<body
    class="h-full bg-gray-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans antialiased selection:bg-gray-200 dark:selection:bg-slate-800">

    <div class="min-h-full flex flex-col justify-center items-center px-6 py-12 lg:px-8 relative overflow-hidden">

        <div class="absolute top-0 left-0 w-full h-full -z-10 opacity-30 dark:opacity-20 pointer-events-none">
            <div class="absolute top-1/4 -left-12 w-64 h-64 bg-gray-300 dark:bg-slate-800 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/4 -right-12 w-96 h-96 bg-gray-200 dark:bg-slate-900 rounded-full blur-3xl">
            </div>
        </div>

        <main class="text-center max-w-xl bg-white px-8 py-8 rounded-xl ">

            <div class="mb-8 flex justify-center">
                <div class="relative inline-block">
                    <h1
                        class="text-7xl sm:text-8xl font-black tracking-tighter text-red-400 dark:text-red-500 select-none">
                        404
                    </h1>

                </div>
            </div>


            <h2 class="text-4xl font-extrabold text-slate-900 dark:text-white mb-4 tracking-tight">Oops! You're lost.
            </h2>
            <p class="text-lg text-slate-600 dark:text-slate-400 mb-10 leading-relaxed">
                We're sorry, but the page you are looking for doesn't exist or has been moved to a new location.
                Why not try going back to the start?
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="<?php echo rtrim(\Core\Config::$URL_PROJECT, '/'); ?>/"
                    class="inline-flex items-center justify-center px-8 py-3 text-base font-semibold text-white bg-gray-900 dark:bg-white dark:text-slate-950 rounded-xl hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg hover:shadow-xl active:scale-95 group">
                    <svg class="w-5 h-5 mr-2 transition-transform group-hover:-translate-x-1" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Home
                </a>

                <button onclick="window.history.back()"
                    class="px-8 py-3 text-base font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border border-gray-200 hover:border-gray-400 dark:hover:border-slate-800 rounded-xl">
                    Go Back
                </button>
            </div>
        </main>

        <footer class="mt-16 text-sm text-slate-400 dark:text-slate-600">
            &copy; <?php echo date('Y'); ?> LilaPHP Framework. All rights reserved.
        </footer>
    </div>

</body>

</html>