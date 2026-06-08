<?php
// nav.php
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
?>
<!-- Responsive Navigation -->
<nav x-data="{ open: false }" class="bg-slate-900 text-slate-100 shadow-xl sticky top-0 z-50 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <a href="dashboard.php" class="flex-shrink-0 font-extrabold text-xl tracking-wider flex items-center gap-2 text-slate-100 hover:text-slate-300 transition-colors">
                    <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    MealManager
                </a>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-2">
                        <a href="dashboard.php" class="<?php echo $currentPage == 'dashboard.php' ? 'bg-slate-800 text-white shadow-inner' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">Dashboard</a>
                        
                        <a href="daily_summary.php" class="<?php echo $currentPage == 'daily_summary.php' ? 'bg-slate-800 text-white shadow-inner' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">Daily</a>
                        
                        <a href="monthly_summary.php" class="<?php echo $currentPage == 'monthly_summary.php' ? 'bg-slate-800 text-white shadow-inner' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">Monthly</a>
                        
                        <a href="market.php" class="<?php echo $currentPage == 'market.php' ? 'bg-slate-800 text-white shadow-inner' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">Bazar</a>
                        
                        <a href="user_history.php" class="<?php echo $currentPage == 'user_history.php' ? 'bg-slate-800 text-white shadow-inner' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">History</a>

                        <?php if ($isAdmin): ?>
                            <a href="admin.php" class="<?php echo $currentPage == 'admin.php' ? 'bg-slate-800 text-emerald-400 shadow-inner ring-1 ring-emerald-500/50' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">Admin Panel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="hidden md:block">
                <a href="logout.php" class="bg-red-500/90 hover:bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Logout
                </a>
            </div>
            <div class="-mr-2 flex md:hidden">
                <!-- Mobile menu button -->
                <button @click="open = !open" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-white transition-colors" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg class="block h-6 w-6" :class="{'hidden': open, 'block': !open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg class="hidden h-6 w-6" :class="{'block': open, 'hidden': !open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="md:hidden border-t border-slate-800" id="mobile-menu" x-show="open" @click.away="open = false" x-transition.opacity.duration.200ms>
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-slate-900">
            <a href="dashboard.php" class="<?php echo $currentPage == 'dashboard.php' ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> block px-3 py-3 rounded-md text-base font-medium transition-colors">Dashboard</a>
            <a href="daily_summary.php" class="<?php echo $currentPage == 'daily_summary.php' ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> block px-3 py-3 rounded-md text-base font-medium transition-colors">Daily Summary</a>
            <a href="monthly_summary.php" class="<?php echo $currentPage == 'monthly_summary.php' ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> block px-3 py-3 rounded-md text-base font-medium transition-colors">Monthly Summary</a>
            <a href="market.php" class="<?php echo $currentPage == 'market.php' ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> block px-3 py-3 rounded-md text-base font-medium transition-colors">Bazar Entry</a>
            <a href="user_history.php" class="<?php echo $currentPage == 'user_history.php' ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> block px-3 py-3 rounded-md text-base font-medium transition-colors">Meal History</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" class="<?php echo $currentPage == 'admin.php' ? 'bg-slate-800 text-emerald-400 border border-emerald-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?> block px-3 py-3 rounded-md text-base font-medium mt-2 transition-colors">Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" class="bg-red-500/90 hover:bg-red-500 text-white block px-3 py-3 rounded-md text-base font-bold mt-4 text-center transition-colors">Logout</a>
        </div>
    </div>
</nav>