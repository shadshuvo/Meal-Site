<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

date_default_timezone_set('Asia/Dhaka');

$currentMonth = date('Y-m');
$dataFile = __DIR__ . "/data/{$currentMonth}.json";
$usersFile = __DIR__ . '/users.json';

// Error handling function
function handleError($message) {
    die(json_encode(['error' => $message]));
}

// Load users data
if (!file_exists($usersFile)) handleError("Users file not found");
$users = json_decode(file_get_contents($usersFile), true) ?? handleError("Invalid users data");

// Load or create monthly data
if (!file_exists($dataFile)) {
    $data = array_fill_keys(
        array_map(fn($i) => sprintf('%02d', $i), range(1, date('t'))),
        ['morning' => array_keys($users), 'night' => array_keys($users), 'guests' => []]
    );
    file_put_contents($dataFile, json_encode($data)) === false && handleError("Unable to create data file");
}

$data = json_decode(file_get_contents($dataFile), true) ?? handleError("Invalid monthly data");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = handlePostRequest($_POST, $data, $currentMonth, $dataFile);
    echo json_encode($response);
    exit;
}

function handlePostRequest($post, &$data, $currentMonth, $dataFile) {
    $date = $post['date'];
    $meal = $post['meal'] ?? null;
    $action = $post['action'];
    $guestCount = (int)($post['guest_count'] ?? 0);

    $currentTime = new DateTime();
    $deadlines = [
        'morning' => new DateTime("$currentMonth-$date 10:00:00"),
        'night' => new DateTime("$currentMonth-$date 18:00:00")
    ];

    if ($action !== 'undo_cancel' && $currentTime > $deadlines[$meal]) {
        return ['success' => false, 'message' => 'Deadline passed'];
    }

    if (strtotime("$currentMonth-$date") < strtotime(date('Y-m-d'))) {
        return ['success' => false, 'message' => 'Cannot modify past dates'];
    }

    switch ($action) {
        case 'cancel':
            $index = array_search($_SESSION['user'], $data[$date][$meal]);
            if ($index !== false) {
                $data[$date][$meal][$index] = $_SESSION['user'] . '_cancelled';
            }
            break;
        case 'undo_cancel':
            $morningIndex = array_search($_SESSION['user'] . '_cancelled', $data[$date]['morning']);
            if ($morningIndex !== false) {
                $data[$date]['morning'][$morningIndex] = $_SESSION['user'];
            }
            $nightIndex = array_search($_SESSION['user'] . '_cancelled', $data[$date]['night']);
            if ($nightIndex !== false) {
                $data[$date]['night'][$nightIndex] = $_SESSION['user'];
            }
            break;
        case 'add_guest':
            $data[$date]['guests'][$_SESSION['user']][$meal] = $guestCount;
            break;
    }

    file_put_contents($dataFile, json_encode($data));
    return ['success' => true];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Manager - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
    <style>
body {
    background-color: #0f172a; /* slate-900 */
    color: #f8fafc;
}
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 15px;
    background-color: transparent !important;
    box-shadow: none !important;
}

/* Guest Controls */
.guest-control {
    display: none;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    background: #1e293b; /* slate-800 */
    padding: 8px;
    border-radius: 6px;
    color: #f8fafc;
    border: 1px solid #334155;
}

.guest-control.active {
    display: flex;
}

.guest-buttons {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-guest {
    padding: 4px 10px;
    border: 1px solid #475569;
    background: #334155;
    border-radius: 4px;
    cursor: pointer;
    color: #f8fafc;
    transition: all 0.2s;
}
.btn-guest:hover {
    background: #475569;
}

.btn-save-guest {
    padding: 4px 12px;
    background: #10b981; /* emerald-500 */
    color: #ffffff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background-color 0.2s;
}
.btn-save-guest:hover {
    background: #059669;
}

.btn-cancel-guest {
    padding: 4px 12px;
    background: #ef4444; /* red-500 */
    color: #ffffff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}
.btn-cancel-guest:hover {
    background: #dc2626;
}

.guest-count-display {
    min-width: 24px;
    text-align: center;
    color: #f8fafc;
    font-weight: bold;
}

/* Calendar Grid */
#calendar {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    padding: 20px 0;
}

.day {
    border: 1px solid #1e293b;
    padding: 15px;
    border-radius: 12px;
    background: #1e293b;
    color: #f8fafc;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s, box-shadow 0.2s;
}
.day:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
}

.day.past {
    opacity: 0.5;
    background: #0f172a;
    border-color: #1e293b;
}

.day.cancelled {
    background: rgba(153, 27, 27, 0.1);
    border-color: rgba(153, 27, 27, 0.3);
}

.day h3 {
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 12px;
    border-bottom: 1px solid #334155;
    padding-bottom: 8px;
}

/* Meal Blocks */
.meal {
    padding: 12px;
    margin: 8px 0;
    border-radius: 8px;
    cursor: pointer;
    background: #334155;
    color: #cbd5e1;
    transition: all 0.2s;
    font-weight: 500;
}
.meal:hover:not(.active) {
    background: #475569;
}

.meal.active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

/* Meal Action Buttons */
.meal-buttons {
    margin-top: 15px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.meal-buttons button {
    padding: 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.meal-buttons .cancel-meal {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.meal-buttons .cancel-meal:hover {
    background: rgba(239, 68, 68, 0.2);
}

.meal-buttons .undo-cancel {
    background: rgba(56, 189, 248, 0.1);
    color: #38bdf8;
    border: 1px solid rgba(56, 189, 248, 0.2);
}
.meal-buttons .undo-cancel:hover {
    background: rgba(56, 189, 248, 0.2);
}

.meal-buttons .btn-add-guest {
    background: rgba(56, 189, 248, 0.1);
    color: #38bdf8; /* sky-400 */
    border: 1px solid rgba(56, 189, 248, 0.4);
    box-shadow: 0 0 8px rgba(56, 189, 248, 0.15);
}
.meal-buttons .btn-add-guest:hover {
    background: rgba(56, 189, 248, 0.2);
    box-shadow: 0 0 12px rgba(56, 189, 248, 0.3);
    border-color: rgba(56, 189, 248, 0.6);
}

.guest-indicator {
    float: right;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.9em;
    color: #10b981;
    background: rgba(16, 185, 129, 0.1);
    padding: 2px 6px;
    border-radius: 12px;
}

/* Header Styles */
.welcome-header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 16px;
    padding: 2.5rem;
    margin-bottom: 2.5rem;
    border: 1px solid #334155;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
}

.main-title {
    font-size: 3.5rem;
    font-weight: 900;
    color: #38bdf8;
    margin: 0 auto 0.5rem;
}

.welcome-text {
    font-size: 1.5rem;
    color: #94a3b8;
    margin-bottom: 1.5rem;
}
.welcome-text .username {
    color: #a78bfa;
    font-weight: bold;
}

@media (max-width: 640px) {
    .main-title {
        font-size: 2.2rem;
    }
    .welcome-text {
        font-size: 1.2rem;
    }
    .welcome-header {
        padding: 1.5rem;
    }
}

.welcome-section {
    position: relative;
    z-index: 1;
    text-align: center;
}

.notice-board {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 0.75rem;
    position: relative;
    overflow: hidden;
    white-space: nowrap;
}

.notice-text {
    color: #fca5a5;
    font-weight: 600;
    display: inline-block;
    animation: scrollText 20s linear infinite;
    padding-left: 100%;
}

@keyframes scrollText {
    from { transform: translateX(0%); }
    to { transform: translateX(-100%); }
}

    </style>
</head>
<body class="bg-slate-900 text-slate-100 antialiased">
    <?php include 'nav.php'; ?>
    <div class="container mx-auto px-4 mt-6">
        <div class="welcome-header">
    <div class="header-content">
        <div class="welcome-section">
            <h1 class="main-title neon-blue">Meal Manager v2.0</h1>
            <p class="welcome-text neon-purple">Welcome back, 
                <span class="username"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
            </p>
            
            <div class="notice-board">
                <p class="notice-text">
                    <?php 
                    $notice = file_exists('notice.txt') ? file_get_contents('notice.txt') : 'No current notices';
                    echo htmlspecialchars($notice);
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>

        <div id="calendar">
            <?php
            $daysInMonth = date('t');
            $today = date('d');
            for ($day = 1; $day <= $daysInMonth; $day++):
                $date = sprintf('%02d', $day);
                $isPast = $day < $today;
                $dayData = $data[$date];
                $isCancelled = in_array($_SESSION['user'] . '_cancelled', $dayData['morning']) || 
                              in_array($_SESSION['user'] . '_cancelled', $dayData['night']);
                $morningActive = in_array($_SESSION['user'], $dayData['morning']);
                $nightActive = in_array($_SESSION['user'], $dayData['night']);
                $morningGuests = $dayData['guests'][$_SESSION['user']]['morning'] ?? 0;
                $nightGuests = $dayData['guests'][$_SESSION['user']]['night'] ?? 0;
                
                $currentTime = new DateTime();
                $morningDeadline = new DateTime("$currentMonth-$date 11:00:00");
                $nightDeadline = new DateTime("$currentMonth-$date 23:00:00");
            ?>
                <div class="day <?php echo $isPast ? 'past' : ''; ?> <?php echo $isCancelled ? 'cancelled' : ''; ?>">
                    <h3><?php echo $day; ?> <?php echo date('F', strtotime($currentMonth)); ?></h3>
                    
                    <!-- Morning Meal Section -->
                    <div class="meal morning <?php echo $morningActive ? 'active' : ''; ?>" 
                         data-date="<?php echo $date; ?>" data-meal="morning">
                        Morning Meal
                        <?php if ($morningGuests > 0): ?>
                            <span class="guest-indicator">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/>
                                </svg>
                                +<?php echo $morningGuests; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="guest-control" id="morning-guest-<?php echo $date; ?>">
                        <div class="guest-buttons">
                            <button class="btn-guest btn-minus" data-meal="morning">-</button>
                            <span class="guest-count-display" data-meal="morning"><?php echo $morningGuests; ?></span>
                            <button class="btn-guest btn-plus" data-meal="morning">+</button>
                        </div>
                        <button class="btn-save-guest" data-date="<?php echo $date; ?>" data-meal="morning">Save</button>
                    </div>

                    <!-- Night Meal Section -->
                    <div class="meal night <?php echo $nightActive ? 'active' : ''; ?>" 
                         data-date="<?php echo $date; ?>" data-meal="night">
                        Night Meal
                        <?php if ($nightGuests > 0): ?>
                            <span class="guest-indicator">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/>
                                </svg>
                                +<?php echo $nightGuests; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="guest-control" id="night-guest-<?php echo $date; ?>">
                        <div class="guest-buttons">
                            <button class="btn-guest btn-minus" data-meal="night">-</button>
                            <span class="guest-count-display" data-meal="night"><?php echo $nightGuests; ?></span>
                            <button class="btn-guest btn-plus" data-meal="night">+</button>
                        </div>
                        <button class="btn-save-guest" data-date="<?php echo $date; ?>" data-meal="night">Save</button>
                    </div>

                    <?php if (!$isPast): ?>
                        <div class="meal-buttons">
                            <?php if ($morningActive && $currentTime <= $morningDeadline): ?>
                                <button class="cancel-meal" data-date="<?php echo $date; ?>" data-meal="morning">
                                    Cancel Morning
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($nightActive && $currentTime <= $nightDeadline): ?>
                                <button class="cancel-meal" data-date="<?php echo $date; ?>" data-meal="night">
                                    Cancel Night
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($isCancelled): ?>
                                <button class="undo-cancel" data-date="<?php echo $date; ?>">
                                    Undo Cancel
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn-add-guest" data-date="<?php echo $date; ?>">
                                Manage Guests
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Cancel meal handler
            $('.cancel-meal').click(function() {
                const date = $(this).data('date');
                const meal = $(this).data('meal');
                if (confirm('Are you sure you want to cancel the ' + meal + ' meal for this day?')) {
                    $.post('dashboard.php', {
                        date: date,
                        meal: meal,
                        action: 'cancel'
                    }, function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                location.reload();
                            } else {
                                alert(result.message || 'Failed to cancel meal');
                            }
                        } catch (e) {
                            alert('Invalid response from server');
                        }
                    });
                }
            });

// Undo cancel handler
$('.undo-cancel').click(function() {
    const date = $(this).data('date');
    if (confirm('Are you sure you want to undo the cancellation for this day?')) {
        $.post('dashboard.php', {
            date: date,
            action: 'undo_cancel'
        }, function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Failed to undo cancellation');
                }
            } catch (e) {
                alert('Invalid response from server');
            }
        });
    }
});

            // Previous guest management handlers remain the same
            $('.btn-add-guest').click(function() {
                const date = $(this).data('date');
                const morningControl = $(`#morning-guest-${date}`);
                const nightControl = $(`#night-guest-${date}`);
                
                morningControl.toggleClass('active');
                nightControl.toggleClass('active');
                
                if (!morningControl.hasClass('active')) {
                    resetGuestCounts(date);
                }
            });

            $('.btn-minus, .btn-plus').click(function() {
                const meal = $(this).data('meal');
                const countDisplay = $(this).closest('.guest-buttons').find('.guest-count-display');
                let count = parseInt(countDisplay.text());
                
                if ($(this).hasClass('btn-minus')) {
                    count = Math.max(0, count - 1);
                } else {
                    count = Math.min(10, count + 1);
                }
                
                countDisplay.text(count);
            });

            $('.btn-save-guest').click(function() {
                const date = $(this).data('date');
                const meal = $(this).data('meal');
                const count = parseInt($(this).closest('.guest-control').find('.guest-count-display').text());

                $.ajax({
                    url: 'dashboard.php',
                    type: 'POST',
                    data: {
                        date: date,
                        meal: meal,
                        action: 'add_guest',
                        guest_count: count
                    },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                location.reload();
                            } else {
                                alert(result.message || 'Failed to update guests');
                            }
                        } catch (e) {
                            alert('Invalid response from server');
                        }
                    },
                    error: function() {
                        alert('Server error occurred');
                    }
                });
            });

            function resetGuestCounts(date) {
                $(`#morning-guest-${date} .guest-count-display`).text('0');
                $(`#night-guest-${date} .guest-count-display`).text('0');
            }
        });
    </script>
</body>
</html>