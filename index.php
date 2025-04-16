<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Database connection
$host = 'localhost';
$db = 'patient_monitoring';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Log user activity
function logActivity($conn, $username, $action) {
    $stmt = $conn->prepare("INSERT INTO user_logs (username, action, timestamp) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $username, $action);
    $stmt->execute();
}

// Handle JSON data request
if (isset($_GET['data'])) {
    $result = $conn->query("SELECT * FROM patients ORDER BY timestamp DESC");

    // $result = $conn->query("SELECT * FROM patients ORDER BY timestamp DESC LIMIT 5");
    if ($result === false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $conn->error]);
        exit();
    }
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['patients' => $patients, 'status' => 'success']);
    exit();
}

// Login
if (!isset($_SESSION['username'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $password = $_POST['password'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['username'] = $username;
                logActivity($conn, $username, "Logged in");
                header("Location: index.php");
                exit();
            } else {
                $login_error = "Invalid password";
            }
        } else {
            $login_error = "User not found";
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patent Monitoring Dashboard | Staff Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg,rgb(121, 225, 226),rgb(224, 239, 243),rgb(114, 229, 237));
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        
        .container {
            background: rgba(20, 20, 40, 0.8);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            text-align: center;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .container::before {
            content: "";
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg,rgb(255, 255, 255),rgb(11, 226, 237),rgb(255, 255, 255));
            border-radius: 18px;
            z-index: -1;
            animation: glowing 3s linear infinite;
            opacity: 0.7;
        }
        
        @keyframes glowing {
            0% { background-position: 0 0; }
            50% { background-position: 400% 0; }
            100% { background-position: 0 0; }
        }
        
        h1 {
            color: #fff;
            margin-bottom: 10px;
            font-size: 2.2em;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .subtitle {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 30px;
            font-size: 0.9em;
        }
        
        input {
            width: 80%;
            padding: 14px;
            margin: 12px 0;
            border: none;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1em;
            outline: none;
            transition: all 0.3s;
        }
        
        input:focus {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 10px rgba(100, 220, 255, 0.5);
        }
        
        input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        button {
            padding: 14px 35px;
            background: linear-gradient(45deg,rgb(0, 88, 96),rgb(2, 146, 183));
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: 600;
            font-size: 1em;
            letter-spacing: 1px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 204, 255, 0.3);
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 0, 204, 0.4);
        }
        
        p {
            color: #ff4d6d;
            margin-top: 15px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Patient Monitoring Dashboard</h1>
        <div class="subtitle">Exclusive Staff Access</div>
        <?php if (isset($login_error)) echo "<p>$login_error</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit" name="login">Login</button>
        </form>
    </div>
</body>
</html>
    <?php
    exit();
}

// Logout
if (isset($_GET['logout'])) {
    logActivity($conn, $_SESSION['username'], "Logged out");
    session_destroy();
    header("Location: index.php");
    exit();
}

// Add patient with manual data entry
if (isset($_GET['add_patient'])) {
    $patientId = filter_input(INPUT_GET, 'patient_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $heartRate = filter_input(INPUT_GET, 'heart_rate', FILTER_VALIDATE_INT);
    $oxygenLevel = filter_input(INPUT_GET, 'oxygen_level', FILTER_VALIDATE_INT);
    $temperature = filter_input(INPUT_GET, 'temperature', FILTER_VALIDATE_FLOAT);
    
    if ($patientId && $heartRate !== false && $oxygenLevel !== false && $temperature !== false) {
        $status = ($heartRate > 100 || $oxygenLevel < 90 || $temperature > 37.5) ? 'Critical' : 'Normal';
        $stmt = $conn->prepare("INSERT INTO patients (patient_id, heart_rate, oxygen_level, temperature, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siids", $patientId, $heartRate, $oxygenLevel, $temperature, $status);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['username'], "Added patient $patientId");
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => "Patient $patientId added successfully"]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => "Error adding patient: " . $conn->error]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid patient data provided']);
    }
    exit();
}

// Edit patient
if (isset($_GET['edit_patient'])) {
    $patientId = filter_input(INPUT_GET, 'patient_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $heartRate = filter_input(INPUT_GET, 'heart_rate', FILTER_VALIDATE_INT);
    $oxygenLevel = filter_input(INPUT_GET, 'oxygen_level', FILTER_VALIDATE_INT);
    $temperature = filter_input(INPUT_GET, 'temperature', FILTER_VALIDATE_FLOAT);
    
    if ($patientId && $heartRate !== false && $oxygenLevel !== false && $temperature !== false) {
        $status = ($heartRate > 100 || $oxygenLevel < 90 || $temperature > 37.5) ? 'Critical' : 'Normal';
        $stmt = $conn->prepare("UPDATE patients SET heart_rate = ?, oxygen_level = ?, temperature = ?, status = ? WHERE patient_id = ?");
        $stmt->bind_param("iids", $heartRate, $oxygenLevel, $temperature, $status, $patientId);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['username'], "Edited patient $patientId");
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => "Patient $patientId updated successfully"]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => "Error editing patient: " . $conn->error]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid patient data provided']);
    }
    exit();
}

// Delete patient
if (isset($_GET['delete'])) {
    $patientId = filter_input(INPUT_GET, 'delete', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ?");
    $stmt->bind_param("s", $patientId);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['username'], "Deleted patient $patientId");
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => "Patient $patientId deleted successfully"]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => "Error deleting patient: " . $conn->error]);
    }
    exit();
}

// Send alert
if (isset($_GET['alert'])) {
    $patientId = filter_input(INPUT_GET, 'alert', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $to = "admin@example.com";
    $subject = "Alert: Patient $patientId Needs Attention";
    $message = "Patient $patientId has critical health metrics.";
    mail($to, $subject, $message);
    exit();
}

// Patient history
if (isset($_GET['history'])) {
    $result = $conn->query("SELECT * FROM patients ORDER BY timestamp DESC LIMIT 24");
    $history = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Patient History</title>
        <style>
            body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #1e3c72, #2a5298); margin: 0; padding: 20px; color: #fff; }
            .container { background: rgba(255, 255, 255, 0.1); padding: 30px; border-radius: 20px; max-width: 800px; margin: 0 auto; }
            h1 { color: #00eaff; margin-bottom: 20px; }
            pre { background: rgba(255, 255, 255, 0.2); padding: 15px; border-radius: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Patient History</h1>
            <div style="overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; background-color: rgba(255,255,255,0.15);">
        <thead>
            <tr style="background-color: rgba(0, 234, 255, 0.3);">
                <th style="padding: 12px; border: 1px solid #fff; color: #00eaff;">Patient ID</th>
                <th style="padding: 12px; border: 1px solid #fff; color: #00eaff;">Heart Rate</th>
                <th style="padding: 12px; border: 1px solid #fff; color: #00eaff;">Oxygen Level</th>
                <th style="padding: 12px; border: 1px solid #fff; color: #00eaff;">Temperature</th>
                <th style="padding: 12px; border: 1px solid #fff; color: #00eaff;">Status</th>
                <th style="padding: 12px; border: 1px solid #fff; color: #00eaff;">Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ccc;"><?= htmlspecialchars($row['patient_id']) ?></td>
                    <td style="padding: 10px; border: 1px solid #ccc;"><?= $row['heart_rate'] ?></td>
                    <td style="padding: 10px; border: 1px solid #ccc;"><?= $row['oxygen_level'] ?></td>
                    <td style="padding: 10px; border: 1px solid #ccc;"><?= $row['temperature'] ?></td>
                    <td style="padding: 10px; border: 1px solid #ccc; color: <?= $row['status'] === 'Critical' ? '#ff4d4d' : '#00ffcc'; ?>; font-weight: bold;"><?= $row['status'] ?></td>
                    <td style="padding: 10px; border: 1px solid #ccc;"><?= $row['timestamp'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

        </div>
    </body>
    </html>
    <?php
    exit();
}

// Export to CSV
if (isset($_GET['export_csv'])) {
    $result = $conn->query("SELECT * FROM patients");
    $patients = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="patients.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Patient ID', 'Heart Rate', 'Oxygen Level', 'Temperature', 'Status', 'Timestamp']);
    foreach ($patients as $patient) {
        fputcsv($output, [$patient['patient_id'], $patient['heart_rate'], $patient['oxygen_level'], $patient['temperature'], $patient['status'], $patient['timestamp']]);
    }
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Patient Monitoring Dashboard</title>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
    
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #f0f8ff, #e6f7ff); /* Light blue gradient background */
        margin: 0;
        padding: 20px;
        color: #333; /* Darker, neutral text color */
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.98);
        padding: 30px;
        border-radius: 16px;
        box-shadow: 0 6px 30px rgba(100, 149, 237, 0.1); /* Cornflower blue shadow */
        border: 1px solid rgba(100, 149, 237, 0.15);
    }
    
    h1 {
        color: #6a5acd; /* Slate blue heading color */
        font-size: 2.6em;
        text-align: center;
        margin-bottom: 25px;
        background: linear-gradient(45deg, #6a5acd, #87ceeb); /* Slate to sky blue gradient */
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .controls {
        display: flex;
        justify-content: center;
        gap: 16px;
        margin: 25px 0;
        flex-wrap: wrap;
    }
    
    .controls button, .controls a {
        padding: 13px 28px;
        background: linear-gradient(45deg, #7b68ee, #add8e6); /* Medium slate to light blue gradient */
        color: #fff;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(100, 149, 237, 0.2);
    }
    
    .controls button:hover, .controls a:hover {
        background: linear-gradient(45deg, #6a5acd, #87ceeb);
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(100, 149, 237, 0.3);
    }
    
    .search-container {
        margin: 25px 0;
    }
    
    .search {
        text-align: center;
        margin-bottom: 12px;
    }
    
    .search input {
        padding: 13px;
        width: 50%;
        border-radius: 10px;
        border: 1px solid #cce0ff; /* Light blue border */
        background: #fff;
        color: #333;
        box-shadow: 0 3px 10px rgba(100, 149, 237, 0.08);
        transition: all 0.3s ease;
    }
    
    .search input:focus {
        border-color: #7b68ee;
        box-shadow: 0 0 0 3px rgba(100, 149, 237, 0.2);
    }
    
    #alerts {
        text-align: center;
        color: #e63946; /* Crimson alert color */
        font-weight: 600;
        padding: 14px;
        background: rgba(230, 57, 70, 0.08);
        border-radius: 10px;
        margin: 12px auto 0;
        width: 50%;
        min-height: 22px;
        border: 1px solid rgba(230, 57, 70, 0.15);
    }
    
    .metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 24px;
        margin-top: 25px;
    }
    
    .metric-card {
        background: #fff;
        padding: 24px;
        border-radius: 14px;
        box-shadow: 0 5px 15px rgba(100, 149, 237, 0.1);
        border: 1px solid rgba(100, 149, 237, 0.1);
        transition: all 0.3s ease;
    }
    
    .metric-card:hover {
        transform: translateY(-7px);
        box-shadow: 0 8px 25px rgba(100, 149, 237, 0.15);
    }
    
    .metric-card h3 {
        color: #6a5acd;
        margin-bottom: 18px;
        font-size: 1.3em;
    }
    
    .metric-card p {
        margin: 10px 0;
        color: #483d8b; /* Dark slate grey text */
    }
    
    .metric-card span {
        font-weight: 600;
        color: #00ced1; /* Dark turquoise span color */
    }
    
    .metric-card button {
        padding: 9px 18px;
        margin: 6px;
        background: linear-gradient(45deg, #ff6347, #ffa07a); /* Tomato to light salmon gradient */
        color: #fff;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 3px 8px rgba(255, 99, 71, 0.2);
    }
    
    .metric-card button:hover {
        background: linear-gradient(45deg, #e63946, #f08080);
        transform: translateY(-2px);
    }
    
    .charts {
        margin-top: 35px;
        background: #fff;
        padding: 25px;
        border-radius: 14px;
        box-shadow: 0 5px 20px rgba(100, 149, 237, 0.1);
        border: 1px solid rgba(100, 149, 237, 0.1);
    }
    
    .message {
        margin: 12px 0;
        padding: 14px;
        border-radius: 10px;
        font-weight: 500;
    }
    
    .success {
        background: rgba(50, 205, 50, 0.15); /* Lime green background */
        color: #32cd32;
        border: 1px solid rgba(50, 205, 50, 0.25);
    }
    
    .error {
        background: rgba(230, 57, 70, 0.12);
        color: #e63946;
        border: 1px solid rgba(230, 57, 70, 0.2);
    }
    
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(60, 60, 60, 0.6); /* Dark grey modal background */
    }
    
    .modal-content {
        background: #fff;
        margin: 12% auto;
        padding: 28px;
        border-radius: 14px;
        width: 82%;
        max-width: 520px;
        box-shadow: 0 8px 30px rgba(60, 60, 60, 0.2);
        animation: modalopen 0.4s ease-out;
    }
    
    @keyframes modalopen {
        from { opacity: 0; transform: translateY(-30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .close {
        color: #778899; /* Light slate grey close button */
        float: right;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .close:hover {
        color: #6a5acd;
        transform: rotate(90deg);
    }
    
    .form-group {
        margin-bottom: 22px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 10px;
        color: #6a5acd;
        font-weight: 500;
        font-size: 1.05em;
    }
    
    .form-group input {
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #cce0ff;
        background: #f0f8ff;
        color: #333;
        transition: all 0.3s ease;
    }
    
    .form-group input:focus {
        border-color: #7b68ee;
        box-shadow: 0 0 0 3px rgba(100, 149, 237, 0.2);
    }
</style>
</head>
<body>
    <div class="container">
        <h1>Patient Monitoring Dashboard</h1>
        <div id="messageBox"></div>
        <div class="controls">
            <button onclick="showAddPatientModal()">Add Patient</button>
            <button onclick="setThresholds()">Set Thresholds</button>
            <button onclick="viewHistory()">View History</button>
            <button onclick="exportCSV()">Export to CSV</button>
            <a href="?logout=true">Logout</a>
        </div>
        <div class="search-container">
    <div class="search">
        <input type="text" id="searchInput" placeholder="Search by Patient ID" onkeyup="searchPatients()">
    </div>
    <div id="alerts"></div>
</div>
<div class="metrics" id="patientList"></div>
        <div class="charts">
            <div id="heartRateChart" style="width: 100%; height: 300px;"></div>
            <div id="oxygenChart" style="width: 100%; height: 300px;"></div>
            <div id="temperatureChart" style="width: 100%; height: 300px;"></div>
        </div>
        <div id="alerts"></div>
    </div>

    <!-- Add Patient Modal -->
    <div id="addPatientModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addPatientModal')">&times;</span>
            <h2>Add New Patient</h2>
            <div class="form-group">
                <label for="patientId">Patient Name:</label>
                <input type="text" id="patientId" required>
            </div>
            <div class="form-group">
                <label for="heartRate">Heart Rate (bpm):</label>
                <input type="number" id="heartRate" min="40" max="200" required>
            </div>
            <div class="form-group">
                <label for="oxygenLevel">Oxygen Level (%):</label>
                <input type="number" id="oxygenLevel" min="70" max="100" required>
            </div>
            <div class="form-group">
                <label for="temperature">Temperature (°C):</label>
                <input type="number" id="temperature" min="35" max="42" step="0.1" required>
            </div>
            <button onclick="addPatient()">Submit</button>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div id="editPatientModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editPatientModal')">&times;</span>
            <h2>Edit Patient</h2>
            <input type="hidden" id="editPatientId">
            <div class="form-group">
                <label for="editHeartRate">Heart Rate (bpm):</label>
                <input type="number" id="editHeartRate" min="40" max="200" required>
            </div>
            <div class="form-group">
                <label for="editOxygenLevel">Oxygen Level (%):</label>
                <input type="number" id="editOxygenLevel" min="70" max="100" required>
            </div>
            <div class="form-group">
                <label for="editTemperature">Temperature (°C):</label>
                <input type="number" id="editTemperature" min="35" max="42" step="0.1" required>
            </div>
            <button onclick="updatePatient()">Update</button>
        </div>
    </div>

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
        google.charts.load('current', { 'packages': ['corechart'] });
        google.charts.setOnLoadCallback(initialize);

        let heartRateData = [['Time', 'Heart Rate']];
        let oxygenData = [['Time', 'Oxygen Level']];
        let temperatureData = [['Time', 'Temperature']];
        let thresholds = { heartRate: 100, oxygenLevel: 90, temperature: 37.5 };
        let allPatients = [];

        function initialize() {
            fetchData();
            if (Notification.permission !== "granted") Notification.requestPermission();
        }

        function fetchData() {
            fetch('index.php?data=true')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        allPatients = data.patients || [];
                        updatePatients(allPatients);
                        updateCharts(allPatients);
                        checkAlerts(allPatients);
                    } else {
                        showMessage('error', data.message || 'Failed to fetch patient data');
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    showMessage('error', 'Error loading data: ' + error.message);
                });
        }

        function updatePatients(patients) {
            const patientList = document.getElementById('patientList');
            patientList.innerHTML = '';
            if (!patients || patients.length === 0) {
                patientList.innerHTML = '<p>No patients found</p>';
                return;
            }
            patients.forEach(patient => {
                const card = document.createElement('div');
                card.className = 'metric-card';
                card.innerHTML = `
                    <h3>Patient ID: ${patient.patient_id}</h3>
                    <p>Heart Rate: <span>${patient.heart_rate} bpm</span></p>
                    <p>Oxygen Level: <span>${patient.oxygen_level}%</span></p>
                    <p>Temperature: <span>${patient.temperature}°C</span></p>
                    <p>Status: <span>${patient.status}</span></p>
                    <button onclick="showEditPatientModal('${patient.patient_id}', ${patient.heart_rate}, ${patient.oxygen_level}, ${patient.temperature})">Edit</button>
                    <button onclick="deletePatient('${patient.patient_id}')">Delete</button>
                `;
                patientList.appendChild(card);
            });
        }

        function updateCharts(patients) {
            if (!patients || patients.length === 0) return;

            // Reset chart data with headers
            heartRateData = [['Time', 'Heart Rate']];
            oxygenData = [['Time', 'Oxygen Level']];
            temperatureData = [['Time', 'Temperature']];

            const now = new Date().toLocaleTimeString();
            patients.forEach(patient => {
                const hr = parseInt(patient.heart_rate, 10);
                const ox = parseInt(patient.oxygen_level, 10);
                const temp = parseFloat(patient.temperature);
                if (isNaN(hr) || isNaN(ox) || isNaN(temp)) {
                    console.error('Invalid patient data:', patient);
                    return;
                }
                heartRateData.push([now, hr]);
                oxygenData.push([now, ox]);
                temperatureData.push([now, temp]);
            });

            try {
                const heartRateChart = new google.visualization.LineChart(document.getElementById('heartRateChart'));
                heartRateChart.draw(google.visualization.arrayToDataTable(heartRateData), {
                    title: 'Heart Rate Over Time', curveType: 'function', legend: { position: 'bottom' }, colors: ['#00eaff'], vAxis: { minValue: 0 }, hAxis: { textPosition: 'none' }, backgroundColor: 'transparent'
                });

                const oxygenChart = new google.visualization.LineChart(document.getElementById('oxygenChart'));
                oxygenChart.draw(google.visualization.arrayToDataTable(oxygenData), {
                    title: 'Oxygen Level Over Time', curveType: 'function', legend: { position: 'bottom' }, colors: ['#00ffcc'], vAxis: { minValue: 0 }, hAxis: { textPosition: 'none' }, backgroundColor: 'transparent'
                });

                const temperatureChart = new google.visualization.LineChart(document.getElementById('temperatureChart'));
                temperatureChart.draw(google.visualization.arrayToDataTable(temperatureData), {
                    title: 'Temperature Over Time', curveType: 'function', legend: { position: 'bottom' }, colors: ['#ffcc00'], vAxis: { minValue: 35 }, hAxis: { textPosition: 'none' }, backgroundColor: 'transparent'
                });
            } catch (e) {
                console.error('Chart Error:', e);
                showMessage('error', 'Error rendering charts: ' + e.message);
            }
        }

        function checkAlerts(patients) {
            const alertsDiv = document.getElementById('alerts');
            alertsDiv.innerHTML = '';
            if (!patients || patients.length === 0) return;
            patients.forEach(patient => {
                if (patient.heart_rate > thresholds.heartRate || patient.oxygen_level < thresholds.oxygenLevel || patient.temperature > thresholds.temperature) {
                    alertsDiv.innerHTML += `<p>Alert: Patient ${patient.patient_id} needs attention!</p>`;
                    sendEmailAlert(patient.patient_id);
                    if (Notification.permission === "granted") {
                        new Notification(`Critical Alert: ${patient.patient_id}`, { body: 'Check vital signs immediately!' });
                    }
                }
            });
        }

        function showAddPatientModal() {
            document.getElementById('addPatientModal').style.display = 'block';
        }

        function showEditPatientModal(patientId, heartRate, oxygenLevel, temperature) {
            document.getElementById('editPatientId').value = patientId;
            document.getElementById('editHeartRate').value = heartRate;
            document.getElementById('editOxygenLevel').value = oxygenLevel;
            document.getElementById('editTemperature').value = temperature;
            document.getElementById('editPatientModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function addPatient() {
            const patientId = document.getElementById('patientId').value;
            const heartRate = document.getElementById('heartRate').value;
            const oxygenLevel = document.getElementById('oxygenLevel').value;
            const temperature = document.getElementById('temperature').value;

            if (!patientId || !heartRate || !oxygenLevel || !temperature) {
                showMessage('error', 'Please fill all fields');
                return;
            }

            fetch(`index.php?add_patient=true&patient_id=${patientId}&heart_rate=${heartRate}&oxygen_level=${oxygenLevel}&temperature=${temperature}`)
                .then(response => response.json())
                .then(data => {
                    showMessage(data.status, data.message);
                    if (data.status === 'success') {
                        closeModal('addPatientModal');
                        fetchData();
                    }
                })
                .catch(error => {
                    console.error('Add Patient Error:', error);
                    showMessage('error', 'Failed to add patient');
                });
        }

        function updatePatient() {
            const patientId = document.getElementById('editPatientId').value;
            const heartRate = document.getElementById('editHeartRate').value;
            const oxygenLevel = document.getElementById('editOxygenLevel').value;
            const temperature = document.getElementById('editTemperature').value;

            if (!patientId || !heartRate || !oxygenLevel || !temperature) {
                showMessage('error', 'Please fill all fields');
                return;
            }

            fetch(`index.php?edit_patient=true&patient_id=${patientId}&heart_rate=${heartRate}&oxygen_level=${oxygenLevel}&temperature=${temperature}`)
                .then(response => response.json())
                .then(data => {
                    showMessage(data.status, data.message);
                    if (data.status === 'success') {
                        closeModal('editPatientModal');
                        fetchData();
                    }
                })
                .catch(error => {
                    console.error('Edit Patient Error:', error);
                    showMessage('error', 'Failed to edit patient');
                });
        }

        function deletePatient(patientId) {
            if (confirm(`Delete Patient ${patientId}?`)) {
                fetch(`index.php?delete=${patientId}`)
                    .then(response => response.json())
                    .then(data => {
                        showMessage(data.status, data.message);
                        if (data.status === 'success') fetchData();
                    })
                    .catch(error => {
                        console.error('Delete Error:', error);
                        showMessage('error', 'Failed to delete patient');
                    });
            }
        }

        function setThresholds() {
            const heartRate = prompt('Set Heart Rate Threshold (default: 100):', thresholds.heartRate);
            const oxygenLevel = prompt('Set Oxygen Level Threshold (default: 90):', thresholds.oxygenLevel);
            const temperature = prompt('Set Temperature Threshold (default: 37.5):', thresholds.temperature);
            thresholds = {
                heartRate: heartRate ? parseInt(heartRate) : thresholds.heartRate,
                oxygenLevel: oxygenLevel ? parseInt(oxygenLevel) : thresholds.oxygenLevel,
                temperature: temperature ? parseFloat(temperature) : thresholds.temperature
            };
            fetchData();
        }

        function viewHistory() {
            window.location.href = 'index.php?history=true';
        }

        function exportCSV() {
            window.location.href = 'index.php?export_csv=true';
        }

        function sendEmailAlert(patientId) {
            fetch(`index.php?alert=${patientId}`).catch(error => console.error('Alert Error:', error));
        }

        function searchPatients() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filteredPatients = allPatients.filter(patient => patient.patient_id.toLowerCase().includes(searchTerm));
            updatePatients(filteredPatients);
        }

        function showMessage(type, message) {
            const messageBox = document.getElementById('messageBox');
            messageBox.innerHTML = `<div class="message ${type}">${message}</div>`;
            setTimeout(() => messageBox.innerHTML = '', 3000);
        }

        setInterval(fetchData, 5000);
        window.onload = fetchData;
    </script>
</body>
</html>