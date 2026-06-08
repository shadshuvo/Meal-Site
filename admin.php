<?php
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}
$usersFile = __DIR__ . '/users.json';
$users = json_decode(file_get_contents($usersFile), true);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['date']) && isset($_POST['meal']) && isset($_POST['user']) && isset($_POST['guest_count'])) {
        $date = $_POST['date'];
        $meal = $_POST['meal'];
        $user = $_POST['user'];
        $guestCount = (int)$_POST['guest_count'];
        
        if ($guestCount > 0) {
            $monthlyFile = __DIR__ . "/data/" . date('Y-m', strtotime($date)) . ".json";
            if (file_exists($monthlyFile)) {
                $monthlyData = json_decode(file_get_contents($monthlyFile), true);
                $day = date('d', strtotime($date));
                if (!isset($monthlyData[$day]['guests'][$user])) {
                    $monthlyData[$day]['guests'][$user] = ['morning' => 0, 'night' => 0];
                }
                $monthlyData[$day]['guests'][$user][$meal] += $guestCount;
                file_put_contents($monthlyFile, json_encode($monthlyData));
                $success = "$guestCount Guest(s) added successfully for $user.";
            } else {
                $error = "Data file for the selected month not found.";
            }
        } else {
            $error = "Guest count must be at least 1.";
        }
    }
    
    // Add image handling
    if (isset($_FILES['image'])) {
        $uploadDir = __DIR__ . '/img/';
        $targetSize = 50 * 1024;
        $file = $_FILES['image'];
        
        if (getimagesize($file["tmp_name"])) {
            $image = imagecreatefromstring(file_get_contents($file["tmp_name"]));
            $nextNumber = count(glob($uploadDir . 'image*.webp')) + 1;
            $newFileName = 'image' . $nextNumber . '.webp';
            
            imagewebp($image, $uploadDir . $newFileName, 80);
            $imageSuccess = "Image uploaded successfully";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_user_meal') {
    $cancelDate = $_POST['cancel_date'];
    $cancelMeal = $_POST['cancel_meal'];
    $cancelUser = $_POST['cancel_user'];
    
    $monthlyFile = __DIR__ . "/data/" . date('Y-m', strtotime($cancelDate)) . ".json";
    
    if (file_exists($monthlyFile)) {
        $monthlyData = json_decode(file_get_contents($monthlyFile), true);
        $day = date('d', strtotime($cancelDate));
        
        if (isset($monthlyData[$day][$cancelMeal])) {
            $index = array_search($cancelUser, $monthlyData[$day][$cancelMeal]);
            if ($index !== false) {
                $monthlyData[$day][$cancelMeal][$index] = $cancelUser . '_cancelled';
                file_put_contents($monthlyFile, json_encode($monthlyData));
                $success = "Successfully cancelled " . $cancelUser . "'s " . $cancelMeal . " meal for " . $cancelDate;
            } else {
                $error = "User not found in meal schedule";
            }
        } else {
            $error = "No meal schedule found for this date";
        }
    } else {
        $error = "No data found for this month";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_cancel') {
    $startDate = new DateTime($_POST['start_date']);
    $endDate = new DateTime($_POST['end_date']);
    $username = $_POST['bulk_cancel_user'];
    $mealTypes = $_POST['meal_types'] ?? [];

    while ($startDate <= $endDate) {
        $currentMonth = $startDate->format('Y-m');
        $monthlyFile = __DIR__ . "/data/{$currentMonth}.json";
        
        if (file_exists($monthlyFile)) {
            $monthlyData = json_decode(file_get_contents($monthlyFile), true);
            $day = $startDate->format('d');
            
            if (isset($monthlyData[$day])) {
                foreach ($mealTypes as $mealType) {
                    if (isset($monthlyData[$day][$mealType])) {
                        // Check for both regular and cancelled username
                        $index = array_search($username, $monthlyData[$day][$mealType]);
                        $cancelledIndex = array_search($username . '_cancelled', $monthlyData[$day][$mealType]);
                        
                        // Only cancel if username exists and isn't already cancelled
                        if ($index !== false && $cancelledIndex === false) {
                            $monthlyData[$day][$mealType][$index] = $username . '_cancelled';
                        }
                    }
                }
            }
            
            file_put_contents($monthlyFile, json_encode($monthlyData));
        }
        
        $startDate->modify('+1 day');
    }
    
    $success = "Successfully cancelled meals for the selected date range.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_user') {
        $newUsername = trim($_POST['new_username']);
        $newPassword = $_POST['new_password'];
        $isAdmin = isset($_POST['new_is_admin']);

        if (empty($newUsername) || empty($newPassword)) {
            $error = "Username and password are required.";
        } elseif (isset($users[$newUsername])) {
            $error = "User already exists!";
        } else {
            $users[$newUsername] = [
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'is_admin' => $isAdmin
            ];
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

            // Update current month data
            $currentMonth = date('Y-m');
            $monthlyFile = __DIR__ . "/data/{$currentMonth}.json";
            
            if (!file_exists($monthlyFile)) {
                // Create file if it doesn't exist (same logic as dashboard.php)
                $monthlyData = array_fill_keys(
                    array_map(fn($i) => sprintf('%02d', $i), range(1, date('t'))),
                    ['morning' => array_keys($users), 'night' => array_keys($users), 'guests' => []]
                );
            } else {
                $monthlyData = json_decode(file_get_contents($monthlyFile), true);
            }

            $currentDay = (int)date('d');
            foreach ($monthlyData as $day => &$dayData) {
                $dayInt = (int)$day;
                $status = ($dayInt < $currentDay) ? $newUsername . '_cancelled' : $newUsername;
                
                if (!in_array($newUsername, $dayData['morning']) && !in_array($newUsername . '_cancelled', $dayData['morning'])) {
                    $dayData['morning'][] = $status;
                }
                if (!in_array($newUsername, $dayData['night']) && !in_array($newUsername . '_cancelled', $dayData['night'])) {
                    $dayData['night'][] = $status;
                }
            }
            file_put_contents($monthlyFile, json_encode($monthlyData));
            
            $success = "User '{$newUsername}' created successfully.";
            $users = json_decode(file_get_contents($usersFile), true); // Refresh users list
        }
    } elseif ($_POST['action'] === 'remove_user') {
        $userToRemove = $_POST['user_to_remove'];
        if ($userToRemove === $_SESSION['user']) {
            $error = "You cannot remove yourself!";
        } elseif (isset($users[$userToRemove])) {
            unset($users[$userToRemove]);
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

            // Update current month data - remove from all days
            $currentMonth = date('Y-m');
            $monthlyFile = __DIR__ . "/data/{$currentMonth}.json";
            if (file_exists($monthlyFile)) {
                $monthlyData = json_decode(file_get_contents($monthlyFile), true);
                foreach ($monthlyData as $day => &$dayData) {
                    $dayData['morning'] = array_values(array_filter($dayData['morning'], fn($u) => $u !== $userToRemove && $u !== $userToRemove . '_cancelled'));
                    $dayData['night'] = array_values(array_filter($dayData['night'], fn($u) => $u !== $userToRemove && $u !== $userToRemove . '_cancelled'));
                    if (isset($dayData['guests'][$userToRemove])) {
                        unset($dayData['guests'][$userToRemove]);
                    }
                }
                file_put_contents($monthlyFile, json_encode($monthlyData));
            }
            $success = "User '{$userToRemove}' removed successfully.";
            $users = json_decode(file_get_contents($usersFile), true); // Refresh users list
        }
    }
}

// Handle notice update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notice') {
    $noticeText = trim($_POST['notice_text']);
    if (empty($noticeText)) {
        unlink('notice.txt');
    } else {
        file_put_contents('notice.txt', $noticeText);
    }
    header('Location: admin.php');
    exit;
}

$existingImages = glob(__DIR__ . '/img/image*.webp');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Manager - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0f172a; /* slate-900 fallback */
            color: #f8fafc;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        
        .form-input {
            @apply w-full px-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 ease-in-out bg-white/50;
        }
        
        .form-select {
            @apply w-full px-4 py-3.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 ease-in-out bg-white/50;
        }
        
        .btn-primary {
            @apply relative px-8 py-3.5 bg-gradient-to-r from-blue-600 to-blue-500 text-white font-semibold rounded-xl 
            hover:from-blue-700 hover:to-blue-600 transition-all duration-200 ease-in-out 
            focus:ring-4 focus:ring-blue-200 shadow-lg shadow-blue-500/30
            active:scale-95 transform;
        }
        
        .input-label {
            @apply block text-sm font-medium text-gray-700 mb-2 ml-1;
        }
        
        .card-hover {
            @apply transition-transform duration-300 hover:scale-[1.02];
        }
        
        .form-container {
            @apply bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-8 border border-gray-200/50 shadow-sm;
        }
    </style>
</head>
<body class="min-h-screen py-0 bg-slate-900 text-slate-100">
    <?php include 'nav.php'; ?>
    <div class="max-w-4xl mx-auto px-4 mt-12">
        <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 p-8 card-hover">
            <!-- Header -->
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-slate-100">
                        Admin Control Panel
                    </h1>
                    <p class="text-slate-400 mt-2">Manage users, notice board, and settings</p>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($success)): ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-emerald-400"><?php echo $success; ?></p>
                    </div>
                </div>
            </div>
            <?php elseif (isset($error)): ?>
            <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-400"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Guest and Cancel Meal Container -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-12">
                <!-- Guest Meal Section -->
                <div class="rounded-2xl p-8 bg-slate-700/50 border border-slate-600 shadow-lg">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-sky-500/20 rounded-lg">
                            <svg class="w-6 h-6 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-slate-200">Add Guest Meal</h2>
                    </div>
                    <form method="post" class="space-y-6">
                        <div class="space-y-4">
                            <div>
                                <label for="date" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select Date</label>
                                <input type="date" id="date" name="date" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-sky-500 bg-slate-800 text-slate-200">
                            </div>
                            <div>
                                <label for="meal" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Meal Type</label>
                                <select id="meal" name="meal" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-sky-500 bg-slate-800 text-slate-200">
                                    <option value="">Choose meal type...</option>
                                    <option value="morning">Morning</option>
                                    <option value="night">Night</option>
                                </select>
                            </div>
                            <div>
                                <label for="user" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select Whose Guest</label>
                                <select id="user" name="user" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-sky-500 bg-slate-800 text-slate-200">
                                    <option value="">Choose...</option>
                                    <?php foreach ($users as $username => $userInfo): ?>
                                        <option value="<?php echo htmlspecialchars($username); ?>">
                                            <?php echo htmlspecialchars($username); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="guest_count" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Number of Guests</label>
                                <input type="number" id="guest_count" name="guest_count" min="1" value="1" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-sky-500 bg-slate-800 text-slate-200">
                            </div>
                        </div>
                        <div class="flex justify-end pt-4">
                            <button type="submit" class="px-8 py-3.5 bg-sky-600 text-white font-semibold rounded-xl hover:bg-sky-700 transition-colors">
                                Add Guest Meal
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Cancel Meal Section -->
                <div class="rounded-2xl p-8 bg-slate-700/50 border border-slate-600 shadow-lg">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-red-500/20 rounded-lg">
                            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-slate-200">Cancel User Meal</h2>
                    </div>
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="action" value="cancel_user_meal">
                        <div class="space-y-4">
                            <div>
                                <label for="cancel_date" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select Date</label>
                                <input type="date" id="cancel_date" name="cancel_date" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-red-500 bg-slate-800 text-slate-200">
                            </div>
                            <div>
                                <label for="cancel_meal" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Meal Type</label>
                                <select id="cancel_meal" name="cancel_meal" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-red-500 bg-slate-800 text-slate-200">
                                    <option value="">Choose meal type...</option>
                                    <option value="morning">Morning</option>
                                    <option value="night">Night</option>
                                </select>
                            </div>
                            <div>
                                <label for="cancel_user" class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select User</label>
                                <select id="cancel_user" name="cancel_user" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-red-500 bg-slate-800 text-slate-200">
                                    <option value="">Choose user...</option>
                                    <?php foreach ($users as $username => $userInfo): ?>
                                        <option value="<?php echo htmlspecialchars($username); ?>">
                                            <?php echo htmlspecialchars($username); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end pt-4">
                            <button type="submit" class="px-8 py-3.5 bg-red-600 text-white font-semibold rounded-xl hover:bg-red-700 transition-colors">
                                Cancel Meal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="max-w-4xl mx-auto mt-12 p-6" x-data="imageUploader()">
        <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 p-8">
            <h2 class="text-2xl font-bold text-slate-200 mb-6">
                Background Images
            </h2>

            <!-- Upload Zone -->
            <div class="mb-8">
                <div 
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="handleDrop($event)"
                    :class="{'border-sky-500 bg-sky-500/10': dragOver, 'border-slate-600 bg-slate-700/50': !dragOver}"
                    class="border-3 border-dashed rounded-xl p-8 text-center transition-all duration-200">
                    
                    <input type="file" 
                           x-ref="fileInput" 
                           @change="handleFileSelect"
                           class="hidden" 
                           accept="image/*" 
                           multiple>

                    <div class="space-y-4">
                        <div class="text-5xl mb-4">📸</div>
                        <h3 class="text-lg font-semibold text-slate-300">
                            Drop images here or click to upload
                        </h3>
                        <p class="text-sm text-slate-500">
                            Supported formats: JPG, PNG (Max 5MB)
                        </p>
                        <button 
                            @click="$refs.fileInput.click()"
                            class="px-6 py-3 bg-sky-600 text-white rounded-lg hover:bg-sky-700 transition duration-200">
                            Select Files
                        </button>
                    </div>
                </div>
            </div>

            <!-- Image Grid -->
            <div class="grid grid-cols-3 gap-6">
                <template x-for="(preview, index) in previews" :key="index">
                    <div class="relative group">
                        <img :src="preview" 
                             class="w-full h-40 object-cover rounded-lg transition duration-200 group-hover:opacity-75 border border-slate-600">
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                            <button 
                                @click="removeImage(index)"
                                class="p-2 bg-red-600 text-white rounded-full hover:bg-red-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Bulk Cancellation Section -->
    <div class="max-w-4xl mx-auto mt-12 px-4">
        <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-red-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-200">Bulk Cancel Meals</h2>
            </div>

            <form method="post" class="space-y-6" onsubmit="return confirm('Are you sure you want to cancel all meals for this date range?');">
                <input type="hidden" name="action" value="bulk_cancel">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">Start Date</label>
                        <input type="date" name="start_date" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-red-500 bg-slate-800 text-slate-200">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">End Date</label>
                        <input type="date" name="end_date" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-red-500 bg-slate-800 text-slate-200">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select User</label>
                    <select name="bulk_cancel_user" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-red-500 bg-slate-800 text-slate-200">
                        <option value="">Choose user...</option>
                        <?php foreach ($users as $username => $userInfo): ?>
                            <option value="<?php echo htmlspecialchars($username); ?>">
                                <?php echo htmlspecialchars($username); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">Select Meals to Cancel</label>
                    <div class="space-y-2">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="meal_types[]" value="morning" class="form-checkbox text-red-500 bg-slate-800 border-slate-600">
                            <span class="ml-2 text-slate-300">Morning Meals</span>
                        </label>
                        <br>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="meal_types[]" value="night" class="form-checkbox text-red-500 bg-slate-800 border-slate-600">
                            <span class="ml-2 text-slate-300">Night Meals</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-8 py-3.5 bg-red-600 text-white font-semibold rounded-xl hover:bg-red-700 transition-colors">
                        Bulk Cancel Meals
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Management Section -->
    <div class="max-w-4xl mx-auto mt-12 px-4">
        <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 p-8">
            <div class="flex items-center gap-3 mb-8">
                <div class="p-2 bg-emerald-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-200">User Management</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <!-- Create User Form -->
                <div>
                    <h3 class="text-lg font-semibold text-slate-300 mb-4">Create New User</h3>
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="action" value="create_user">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">Username</label>
                            <input type="text" name="new_username" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-emerald-500 bg-slate-800 text-slate-200" placeholder="Enter username">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">Password</label>
                            <input type="password" name="new_password" required class="w-full px-4 py-3.5 rounded-xl border border-slate-600 focus:ring-2 focus:ring-emerald-500 bg-slate-800 text-slate-200" placeholder="Enter password">
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="new_is_admin" class="form-checkbox text-emerald-500 bg-slate-800 border-slate-600 h-5 w-5 rounded">
                                <span class="ml-2 text-slate-300 font-medium">Make this user an Admin</span>
                            </label>
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="w-full px-8 py-3.5 bg-emerald-600 text-white font-semibold rounded-xl hover:bg-emerald-700 transition-colors">
                                Create User
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Remove User List -->
                <div>
                    <h3 class="text-lg font-semibold text-slate-300 mb-4">Existing Users</h3>
                    <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
                        <?php foreach ($users as $username => $userInfo): ?>
                            <div class="flex items-center justify-between p-4 bg-slate-700/50 rounded-xl border border-slate-600 transition-all hover:border-emerald-500/50 group">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 font-bold">
                                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-200"><?php echo htmlspecialchars($username); ?></p>
                                        <p class="text-xs text-slate-400 uppercase font-semibold tracking-wider">
                                            <?php echo $userInfo['is_admin'] ? 'Admin' : 'Member'; ?>
                                        </p>
                                    </div>
                                </div>
                                <?php if ($username !== $_SESSION['user']): ?>
                                    <form method="post" onsubmit="return prompt('Are you sure you want to remove <?php echo $username; ?>?\nAll their active meal data for this month will be removed, but history is preserved.\n\nType \'Delete\' to confirm:') === 'Delete';">
                                        <input type="hidden" name="action" value="remove_user">
                                        <input type="hidden" name="user_to_remove" value="<?php echo htmlspecialchars($username); ?>">
                                        <button type="submit" class="p-2 text-slate-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-all">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notice Board Management -->
    <div class="max-w-4xl mx-auto mt-12 px-4 mb-12">
        <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 p-8">
            <h2 class="text-2xl font-bold text-slate-200 mb-6">Notice Board Management</h2>
            <form method="post" action="admin.php">
                <input type="hidden" name="action" value="update_notice">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2 ml-1">Notice Text</label>
                        <textarea name="notice_text" rows="3" 
                            class="w-full px-4 py-3 rounded-xl border border-slate-600 focus:ring-2 focus:ring-amber-500 bg-slate-800 text-slate-200 transition-all duration-200"
                        ><?php echo file_exists('notice.txt') ? file_get_contents('notice.txt') : ''; ?></textarea>
                    </div>
                    <button type="submit" class="px-6 py-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition duration-200 font-medium">
                        Update Notice
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

<script>
function imageUploader() {
    return {
        dragOver: false,
        previews: [],
        
        handleDrop(e) {
            this.dragOver = false;
            this.handleFiles(e.dataTransfer.files);
        },
        
        handleFileSelect(e) {
            this.handleFiles(e.target.files);
        },
        
        handleFiles(files) {
            [...files].forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        this.previews.push(e.target.result);
                    };
                    reader.readAsDataURL(file);
                    
                    // Upload file
                    const formData = new FormData();
                    formData.append('image', file);
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                }
            });
        },
        
        removeImage(index) {
            this.previews.splice(index, 1);
        }
    }
}
</script>

<style>
.border-3 {
    border-width: 3px;
}
</style>