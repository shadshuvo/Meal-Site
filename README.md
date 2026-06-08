# MealManager v2.0

MealManager is a lightweight, flat-file PHP web application designed to manage daily meals, track guest counts, calculate monthly summaries, and record "Bazar" (market) expenses for a group of people. 

It is designed to run easily on local development environments (like XAMPP) or shared hosting without the need for a complex database like MySQL, as all data is stored securely in JSON files.

## 🌟 Key Features

### 1. Modern Dark Theme UI
The entire application features a cohesive, premium "darkish vibe" utilizing Tailwind CSS v3 (Slate color palette).
*   Deep slate backgrounds (`slate-900`) with sleek, interactive cards (`slate-800`).
*   Responsive navigation menu ("hamburger" style on mobile) using Alpine.js.
*   Subtle neon-style accents (cyan, emerald, purple) for active states and interactive buttons on the dashboard.

### 2. User Dashboard
The central hub for daily activities.
*   **Meal Toggling:** Users can easily cancel or undo cancellations for their Morning and Night meals.
*   **Time Restrictions:** Enforces deadlines for meal cancellations (e.g., Morning meals must be cancelled before 8:00 AM, Night meals before 2:00 PM).
*   **Guest Management:** Users can add multiple guest meals to their tally for specific dates.
*   **Notice Board:** Displays real-time scrolling updates from the administrators.

### 3. Comprehensive Summaries
*   **Daily Summary:** A quick breakdown of who is eating and who has cancelled for any specific date.
*   **Monthly Summary:** Aggregates total regular meals and guest meals for every user across the entire month.
*   **User History:** A day-by-day calendar view for individual users, showing exact meal statuses (Active/Cancelled) and guest counts for a selected month.
*   **Personal Bulk Cancel:** Logged-in users can bulk-cancel their own upcoming meals for a specific date range directly from the Monthly Summary page.

### 4. Admin Control Panel
Dedicated tools for users with administrative privileges.
*   **User Management:** Create new users (with BCrypt password hashing) and remove existing users. Removing a user mid-month safely clears their active entries without destroying historical data files.
*   **Admin Bulk Cancel:** Force-cancel meals for any user over a specified date range.
*   **Manual Guest Entry:** Add specific counts of guest meals for any user on any date.
*   **Notice Board Control:** Update the scrolling text seen on the dashboard.
*   **Background Images:** Upload and manage background images (converted to WebP).

### 5. Market / Bazar Entry
*   Users can log daily market expenses, including a text breakdown of items bought and the total amount spent.
*   Records are grouped by month and easily reviewable.

---

## 📂 File Structure & Data Storage

MealManager uses a flat-file architecture, meaning there is no SQL database setup required.

```text
/
├── index.php             # Login page
├── dashboard.php         # Main user interface
├── admin.php             # Administrator controls
├── nav.php               # Unified responsive navigation component
├── daily_summary.php     # Daily status view
├── monthly_summary.php   # Monthly aggregation & personal bulk cancel
├── user_history.php      # Individual user calendar view
├── market.php            # Bazar expense logging
├── logout.php            # Session termination
├── style.css             # Base custom styles
├── users.json            # User credentials (BCrypt hashed) & admin flags
├── notice.txt            # Current scrolling notice text
├── data/                 # Folder containing meal logs
│   ├── 2024-10.json      # YYYY-MM format meal data
│   └── 2024-11.json
├── market_data/          # Folder containing bazar expense logs
│   └── 2024-11.json      
└── img/                  # Uploaded background images
```

### Data Architecture
*   **`users.json`**: Stores user authentication. Passwords are mathematically hashed using `password_hash()` (BCrypt) ensuring they are never stored in plain text.
*   **`data/YYYY-MM.json`**: Stores the meal schedule. If a user cancels a meal, their name string is appended with `_cancelled` (e.g., `"Shuvo_cancelled"`), preserving the record of their cancellation rather than just deleting the entry.

---

## 🚀 Setup Instructions

1.  **Environment:** Ensure you have a PHP environment running (e.g., XAMPP, WAMP, or a Linux server with PHP 7.4+).
2.  **Files:** Place all project files into your web server's document root (e.g., `C:\xampp\htdocs\Meal` or `/var/www/html/`).
3.  **Permissions:** Ensure the PHP script has read/write permissions for the following files and directories:
    *   `users.json`
    *   `notice.txt`
    *   `/data/` directory
    *   `/market_data/` directory
    *   `/img/` directory
4.  **Initial Access:** If `users.json` is missing, you may need to manually create it with an initial admin user to access the system, or run a setup script if provided.

## 🛠️ Technology Stack
*   **Backend:** PHP (Session management, JSON File I/O)
*   **Frontend:** HTML5, Alpine.js (Interactivity)
*   **Styling:** Tailwind CSS v3 (CDN), Custom CSS
*   **Security:** BCrypt Password Hashing