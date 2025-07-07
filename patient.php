<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: index.php");
    exit();
}

// Fetch patient's name
$patient_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$stmt->bind_result($patient_name);
$stmt->fetch();
$stmt->close();

// Fetch available doctors
$doctors = $conn->query("SELECT id, username FROM users WHERE role = 'doctor'");

// Handle appointment booking
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_appointment'])) {
    $date = $_POST['date'];
    $time = $_POST['time'];
    $doctor_id = $_POST['doctor'];

    // Validate date (only allow booking for next 5 days)
    $today = date("Y-m-d");
    $max_date = date("Y-m-d", strtotime("+5 days"));

    if ($date < $today || $date > $max_date) {
        echo "<script>alert('Invalid date! Please select a date within the next 5 days.');</script>";
    } elseif ($time < "19:00" || $time > "21:00") {
        echo "<script>alert('Invalid time! Please select a time between 7:00 PM and 9:00 PM.');</script>";
    } else {
        // Insert appointment into database
        $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, date, time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $patient_id, $doctor_id, $date, $time);

        if ($stmt->execute()) {
            echo "<script>alert('Appointment booked successfully!'); window.location='patient.php';</script>";
        } else {
            echo "<script>alert('Error booking appointment. Please try again.');</script>";
        }
        $stmt->close();
    }
}

// Fetch appointments for the logged-in patient
$appointments = $conn->query("
    SELECT appointments.id, appointments.date, appointments.time, users.username AS doctor_name 
    FROM appointments 
    INNER JOIN users ON appointments.doctor_id = users.id 
    WHERE appointments.patient_id = $patient_id
");

// Handle appointment deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $appointment_id, $patient_id);

    if ($stmt->execute()) {
        echo "<script>alert('Appointment deleted successfully!'); window.location='patient.php';</script>";
    } else {
        echo "<script>alert('Error deleting appointment.');</script>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Patient Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Welcome, <?php echo htmlspecialchars($patient_name); ?>!</h1>
        <h2>Book an Appointment</h2>
        <form action="patient.php" method="post">
            <label>Select Date:</label>
            <input type="date" name="date" id="date" required>

            <label>Select Time Slot:</label>
            <input type="time" name="time" id="time" required>

            <label>Select Doctor:</label>
            <select name="doctor" required>
                <?php while ($doctor = $doctors->fetch_assoc()) {
                    echo "<option value='{$doctor['id']}'>{$doctor['username']}</option>";
                } ?>
            </select>

            <button type="submit" name="book_appointment">Book Appointment</button>
        </form>

        <h2>Your Appointments</h2>
        <?php if ($appointments->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Doctor Name</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Action</th>
                </tr>
                <?php while ($appointment = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['date']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['time']); ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                <button type="submit" name="delete_appointment" class="delete-btn">Cancel</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>No appointments scheduled.</p>
        <?php endif; ?>

        <a href="logout.php" class="btn">Logout</a>
    </div>

    <script>
        // Set min and max date dynamically
        let today = new Date();
        let maxDate = new Date();
        maxDate.setDate(today.getDate() + 5);

        let todayStr = today.toISOString().split("T")[0];
        let maxDateStr = maxDate.toISOString().split("T")[0];

        document.getElementById("date").setAttribute("min", todayStr);
        document.getElementById("date").setAttribute("max", maxDateStr);

        // Restrict time slot between 7:00 PM and 9:00 PM
        document.getElementById("time").setAttribute("min", "19:00");
        document.getElementById("time").setAttribute("max", "21:00");
    </script>
</body>
</html>
