<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$month = date('Y-m', strtotime($date));
$dataFile = "data/{$month}.json";
if (!file_exists($dataFile)) {
    $error = "Data file for the selected month not found.";
} else {
    $dataJson = file_get_contents($dataFile);
    if ($dataJson === false) {
        $error = "Unable to read data file. Please check file permissions.";
    } else {
        $monthlyData = json_decode($dataJson, true);
        if ($monthlyData === null) {
            $error = "Invalid JSON in data file. Please check the file contents.";
        } else {
            $day = date('d', strtotime($date));
            $dailyData = isset($monthlyData[$day]) ? $monthlyData[$day] : ['morning' => [], 'night' => [], 'guests' => []];
            $morningMeals = isset($dailyData['morning']) ? $dailyData['morning'] : [];
            $nightMeals = isset($dailyData['night']) ? $dailyData['night'] : [];
            $guestMeals = isset($dailyData['guests']) ? $dailyData['guests'] : [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Meal Summary - <?php echo htmlspecialchars($date); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a; /* slate-900 */
            color: #f8fafc;
        }
        
        .card {
            background-color: #1e293b; /* slate-800 */
            border: 1px solid #334155;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        
        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .summary-table th {
            background-color: #0f172a; /* slate-900 */
            color: #cbd5e1;
            font-weight: 600;
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid #334155;
        }
        
        .summary-table td {
            padding: 1rem;
            border-bottom: 1px solid #334155;
            color: #f8fafc;
        }
        
        .summary-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-active {
            color: #10b981; /* emerald-500 */
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-cancelled {
            color: #ef4444; /* red-500 */
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        @media (max-width: 640px) {
            .container {
                padding: 1rem;
            }
            
            .summary-table th,
            .summary-table td {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="p-4 md:p-8 mt-6">
        <div class="max-w-6xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-100 mb-2">Daily Meal Summary</h1>
                    <p class="text-slate-400"><?php echo htmlspecialchars($date); ?></p>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-500/10 border-l-4 border-red-500 p-4 mb-8 rounded-lg">
                    <p class="text-red-400"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Morning Meals Card -->
                    <div class="card p-6">
                        <h2 class="text-xl font-semibold text-slate-200 mb-4">Morning Meals</h2>
                        <table class="summary-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($morningMeals as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user); ?></td>
                                        <td>
                                            <span class="<?php echo strpos($user, '_cancelled') !== false ? 'status-cancelled' : 'status-active'; ?>">
                                                <?php echo strpos($user, '_cancelled') !== false ? 'Cancelled' : 'Active'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($morningMeals)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-slate-500 py-4">No morning meals scheduled</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Night Meals Card -->
                    <div class="card p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Night Meals</h2>
                        <table class="summary-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nightMeals as $user): ?>
                                    <tr>
                                        <td class="text-gray-700"><?php echo htmlspecialchars($user); ?></td>
                                        <td>
                                            <span class="<?php echo strpos($user, '_cancelled') !== false ? 'status-cancelled' : 'status-active'; ?>">
                                                <?php echo strpos($user, '_cancelled') !== false ? 'Cancelled' : 'Active'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($nightMeals)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-gray-500 py-4">No night meals scheduled</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Guest Meals Card -->
                    <div class="card p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Guest Meals</h2>
                        <table class="summary-table">
                            <thead>
                                <tr>
                                    <th>Host</th>
                                    <th>Meal</th>
                                    <th>Guests</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guestMeals as $host => $meals): ?>
                                    <?php foreach ($meals as $meal => $count): ?>
                                        <tr>
                                            <td class="text-gray-700"><?php echo htmlspecialchars($host); ?></td>
                                            <td class="text-gray-700"><?php echo htmlspecialchars($meal); ?></td>
                                            <td class="text-gray-700"><?php echo htmlspecialchars($count); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <?php if (empty($guestMeals)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-gray-500 py-4">No guest meals scheduled</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>