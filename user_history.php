<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

date_default_timezone_set('Asia/Dhaka');

// Load users data
$usersFile = __DIR__ . '/users.json';
if (!file_exists($usersFile)) {
    die("Users file not found");
}
$users = json_decode(file_get_contents($usersFile), true);

// Get available months from data directory
function getAvailableMonths() {
    $files = glob(__DIR__ . '/data/*.json');
    return array_map(function($file) {
        return basename($file, '.json');
    }, $files);
}

$availableMonths = getAvailableMonths();
rsort($availableMonths);

// Handle form submission
$selectedUser = $_GET['user'] ?? '';
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Get user's meal data for selected month
$userData = [];
if ($selectedUser && $selectedMonth) {
    $dataFile = __DIR__ . "/data/{$selectedMonth}.json";
    if (file_exists($dataFile)) {
        $monthData = json_decode(file_get_contents($dataFile), true);
        foreach ($monthData as $day => $dayData) {
            $userData[$day] = [
                'morning' => in_array($selectedUser, $dayData['morning']) ? 'active' : 
                           (in_array($selectedUser . '_cancelled', $dayData['morning']) ? 'cancelled' : 'inactive'),
                'night' => in_array($selectedUser, $dayData['night']) ? 'active' : 
                         (in_array($selectedUser . '_cancelled', $dayData['night']) ? 'cancelled' : 'inactive'),
                'guests' => isset($dayData['guests'][$selectedUser]) ? $dayData['guests'][$selectedUser] : []
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Meal History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
    <style>
        /* Status badges */
        .meal-status {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: #34d399; /* emerald-400 */
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-cancelled {
            background-color: rgba(239, 68, 68, 0.1);
            color: #f87171; /* red-400 */
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-inactive {
            background-color: rgba(148, 163, 184, 0.1);
            color: #94a3b8; /* slate-400 */
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        
        /* Card styles */
        .day-card {
            background-color: #1e293b; /* slate-800 */
            border: 1px solid #334155; /* slate-700 */
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        
        /* Card background colors based on status */
        .day-card.has-cancelled {
            background-color: rgba(153, 27, 27, 0.15); /* Dark red tint */
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .day-card.all-active {
            background-color: rgba(16, 185, 129, 0.05); /* Dark emerald tint */
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        /* Hover effects */
        .day-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }
        
        /* Responsive grid */
        @media (min-width: 640px) {
            .grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 768px) {
            .grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mt-6">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-slate-100">User Meal History</h1>
        </div>

        <!-- Selection Form -->
        <div class="bg-slate-800 rounded-2xl shadow-xl border border-slate-700 p-8 mb-8">
            <form method="GET" class="space-y-4 sm:space-y-0 sm:flex sm:items-center sm:space-x-4">
                <div class="w-full sm:w-1/3">
                    <label for="user" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select User</label>
                    <select name="user" id="user" class="mt-1 block w-full px-4 py-2.5 text-base border-slate-600 bg-slate-900 text-slate-200 focus:outline-none focus:ring-sky-500 focus:border-sky-500 rounded-xl">
                        <option value="">Choose a user...</option>
                        <?php foreach ($users as $username => $userInfo): ?>
                            <option value="<?php echo htmlspecialchars($username); ?>" 
                                    <?php echo $selectedUser === $username ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($username); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="w-full sm:w-1/3">
                    <label for="month" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select Month</label>
                    <select name="month" id="month" class="mt-1 block w-full px-4 py-2.5 text-base border-slate-600 bg-slate-900 text-slate-200 focus:outline-none focus:ring-sky-500 focus:border-sky-500 rounded-xl">
                        <?php foreach ($availableMonths as $month): ?>
                            <option value="<?php echo htmlspecialchars($month); ?>"
                                    <?php echo $selectedMonth === $month ? 'selected' : ''; ?>>
                                <?php echo date('F Y', strtotime($month . '-01')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:mt-8">
                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl shadow-sm text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-colors">
                        View History
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Display -->
        <?php if ($selectedUser && !empty($userData)): ?>
            <div class="bg-slate-800 rounded-2xl shadow-xl overflow-hidden border border-slate-700">
                <div class="px-6 py-5 border-b border-slate-700">
                    <h2 class="text-xl font-bold text-slate-200">
                        Meal History for <span class="text-sky-400"><?php echo htmlspecialchars($selectedUser); ?></span> - 
                        <?php echo date('F Y', strtotime($selectedMonth . '-01')); ?>
                    </h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-6">
                    <?php foreach ($userData as $day => $dayData): 
                        $hasCancelled = $dayData['morning'] === 'cancelled' || $dayData['night'] === 'cancelled';
                        $allActive = $dayData['morning'] === 'active' && $dayData['night'] === 'active';
                        $cardClass = $hasCancelled ? 'has-cancelled' : ($allActive ? 'all-active' : '');
                    ?>
                        <div class="day-card <?php echo $cardClass; ?>">
                            <div class="font-bold text-slate-200 mb-4 pb-2 border-b border-slate-700/50">
                                <?php echo date('j F', strtotime($selectedMonth . '-' . $day)); ?>
                            </div>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-slate-400">Morning:</span>
                                    <span class="meal-status status-<?php echo $dayData['morning']; ?>">
                                        <?php echo ucfirst($dayData['morning']); ?>
                                    </span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-slate-400">Night:</span>
                                    <span class="meal-status status-<?php echo $dayData['night']; ?>">
                                        <?php echo ucfirst($dayData['night']); ?>
                                    </span>
                                </div>

                                <?php if (!empty($dayData['guests'])): ?>
                                    <div class="mt-3 pt-3 border-t border-slate-700/50">
                                        <span class="text-sm font-bold text-sky-400">Guests:</span>
                                        <div class="text-sm font-medium text-slate-300 mt-1">
                                            <?php foreach ($dayData['guests'] as $meal => $count): ?>
                                                <div><?php echo ucfirst($meal) . ': ' . $count; ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($selectedUser): ?>
            <div class="bg-slate-800 rounded-2xl shadow-xl p-8 border border-slate-700">
                <p class="text-slate-400 text-center font-medium">No meal data available for this month.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>