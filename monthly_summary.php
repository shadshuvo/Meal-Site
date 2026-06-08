<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'user_bulk_cancel') {
    $startDate = new DateTime($_POST['start_date']);
    $endDate = new DateTime($_POST['end_date']);
    $username = $_SESSION['user'];
    $mealTypes = $_POST['meal_types'] ?? [];

    $today = new DateTime(date('Y-m-d'));
    if ($startDate <= $today) {
        $error = "You can only bulk cancel meals for tomorrow or future dates.";
    } else {
        while ($startDate <= $endDate) {
            $currentMonthIter = $startDate->format('Y-m');
            $monthlyFile = __DIR__ . "/data/{$currentMonthIter}.json";
            
            if (file_exists($monthlyFile)) {
                $monthlyDataIter = json_decode(file_get_contents($monthlyFile), true);
                $dayIter = $startDate->format('d');
                
                if (isset($monthlyDataIter[$dayIter])) {
                    foreach ($mealTypes as $mealType) {
                        if (isset($monthlyDataIter[$dayIter][$mealType])) {
                            $index = array_search($username, $monthlyDataIter[$dayIter][$mealType]);
                            $cancelledIndex = array_search($username . '_cancelled', $monthlyDataIter[$dayIter][$mealType]);
                            
                            if ($index !== false && $cancelledIndex === false) {
                                $monthlyDataIter[$dayIter][$mealType][$index] = $username . '_cancelled';
                            }
                        }
                    }
                }
                file_put_contents($monthlyFile, json_encode($monthlyDataIter));
            }
            $startDate->modify('+1 day');
        }
        $success = "Successfully cancelled your meals for the selected date range.";
    }
}

function getAvailableMonths($dataDir) {
    $files = glob($dataDir . '/*.json');
    $months = array_map(function($file) {
        return basename($file, '.json');
    }, $files);
    sort($months);
    return $months;
}

$dataDir = __DIR__ . '/data';
$availableMonths = getAvailableMonths($dataDir);
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Check if today is the first day of the month and the time is before 1:00 AM
$today = new DateTime();
$firstDayOfMonth = new DateTime('first day of this month');
if ($today->format('Y-m-d') === $firstDayOfMonth->format('Y-m-d') && $today->format('H:i') < '01:00') {
    $currentMonth = date('Y-m', strtotime('-1 month'));
}

$dataFile = "{$dataDir}/{$currentMonth}.json";
$usersFile = __DIR__ . '/users.json';

// If the data file for the current month does not exist, fall back to the previous month
if (!file_exists($dataFile)) {
    $currentMonth = date('Y-m', strtotime('-1 month'));
    $dataFile = "{$dataDir}/{$currentMonth}.json";
}

if (!file_exists($dataFile)) {
    die("Error: Monthly data file not found. Please ensure the current month's data has been initialized.");
}
if (!file_exists($usersFile)) {
    die("Error: users.json file not found. Please check the file location and permissions.");
}

$monthlyData = json_decode(file_get_contents($dataFile), true);
if ($monthlyData === null) {
    die("Error: Invalid JSON in monthly data file. Please check the file contents.");
}

$users = json_decode(file_get_contents($usersFile), true);
if ($users === null) {
    die("Error: Invalid JSON in users.json file. Please check the file contents.");
}

$summary = [];
foreach ($users as $username => $userInfo) {
    $summary[$username] = [
        'meals' => 0,
        'guest_meals' => 0
    ];
}

foreach ($monthlyData as $day => $meals) {
    foreach ($meals as $mealType => $participants) {
        if ($mealType === 'guests') {
            foreach ($participants as $host => $guestMeals) {
                foreach ($guestMeals as $meal => $count) {
                    if (!isset($summary[$host])) {
                        $summary[$host] = ['meals' => 0, 'guest_meals' => 0];
                    }
                    $summary[$host]['guest_meals'] += $count;
                }
            }
        } else {
            foreach ($participants as $participant) {
                // Check if the participant is not canceled
                if (strpos($participant, '_cancel') === false) {
                    if (!isset($summary[$participant])) {
                        $summary[$participant] = ['meals' => 0, 'guest_meals' => 0];
                    }
                    $summary[$participant]['meals'] += 1;
                }
            }
        }
    }
}

function formatMonth($month) {
    return date('F Y', strtotime($month . '-01'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Summary</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
    <style>
        .custom-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
        
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mt-6">
        <!-- Main Content Card -->
        <div class="bg-slate-800 rounded-2xl shadow-xl border border-slate-700 p-8 mb-8 transition-all duration-300">
            <h1 class="text-4xl font-bold text-slate-100 mb-8">Monthly Summary</h1>
            
            <!-- Month Selector -->
            <form method="GET" action="monthly_summary.php" class="mb-10">
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                    <label for="month" class="text-slate-300 font-semibold text-lg">Select Month:</label>
                    <div class="flex gap-3 w-full sm:w-auto">
                        <select name="month" id="month" 
                                class="custom-select block w-full sm:w-64 rounded-xl border border-slate-600 bg-slate-900 text-slate-200 shadow-sm focus:border-sky-500 focus:ring-sky-500 py-2.5 pl-4 text-base transition-colors duration-200">
                            <?php foreach ($availableMonths as $month): ?>
                                <option value="<?php echo htmlspecialchars($month); ?>" <?php echo $month === $currentMonth ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(formatMonth($month)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 shadow-md transition-all duration-200 ease-in-out transform hover:-translate-y-0.5">
                            View Summary
                        </button>
                    </div>
                </div>
            </form>

            <!-- Summary Header -->
            <h2 class="text-2xl font-bold text-slate-200 mb-6 pb-2 border-b border-slate-700">
                Summary for <?php echo htmlspecialchars(formatMonth($currentMonth)); ?>
            </h2>

            <!-- Summary Table -->
            <div class="overflow-hidden rounded-xl border border-slate-700 shadow-sm">
                <table class="min-w-full divide-y divide-slate-700">
                    <thead class="bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                User
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Total Meals
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Guest Meals
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-slate-800 divide-y divide-slate-700">
                        <?php foreach ($summary as $username => $data): ?>
                            <tr class="hover:bg-slate-700/50 transition-all duration-200">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-sky-500/20 flex items-center justify-center mr-3">
                                            <span class="text-sky-400 font-bold">
                                                <?php echo strtoupper(substr($username, 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="text-sm font-medium text-slate-200">
                                            <?php echo htmlspecialchars($username); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <?php echo htmlspecialchars($data['meals']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                        <?php echo htmlspecialchars($data['guest_meals']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- User Bulk Cancellation Section -->
        <div class="bg-slate-800 rounded-2xl shadow-xl border border-slate-700 p-8 transition-all duration-300">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-red-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-200">Bulk Cancel Upcoming Meals</h2>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 shadow-sm">
                    <p class="text-sm font-medium text-emerald-400"><?php echo $success; ?></p>
                </div>
            <?php elseif (isset($error)): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 shadow-sm">
                    <p class="text-sm font-medium text-red-400"><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="monthly_summary.php" class="space-y-6" onsubmit="return confirm('Are you sure you want to cancel your meals for this date range?');">
                <input type="hidden" name="action" value="user_bulk_cancel">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">Start Date</label>
                        <input type="date" name="start_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-red-500 bg-slate-900 text-slate-200">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">End Date</label>
                        <input type="date" name="end_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-red-500 bg-slate-900 text-slate-200">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select Meals to Cancel</label>
                    <div class="space-y-2">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="meal_types[]" value="morning" class="form-checkbox text-red-500 bg-slate-900 border-slate-600 h-5 w-5 rounded">
                            <span class="ml-2 text-slate-300 font-medium">Morning Meals</span>
                        </label>
                        <br>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="meal_types[]" value="night" class="form-checkbox text-red-500 bg-slate-900 border-slate-600 h-5 w-5 rounded">
                            <span class="ml-2 text-slate-300 font-medium">Night Meals</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-start pt-2">
                    <button type="submit" class="px-8 py-3.5 bg-red-600 text-white font-semibold rounded-xl hover:bg-red-700 transition-colors">
                        Cancel My Meals
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>