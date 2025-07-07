<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

// Fetch doctor's name
$doctor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$stmt->bind_result($doctor_name);
$stmt->fetch();
$stmt->close();

// Mark appointments as completed
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complete_appointments'])) {
    if (!empty($_POST['appointment_ids'])) {
        foreach ($_POST['appointment_ids'] as $appointment_id) {
            $update_stmt = $conn->prepare("UPDATE appointments SET completed = 1 WHERE id = ?");
            $update_stmt->bind_param("i", $appointment_id);
            $update_stmt->execute();
        }
        echo "<script>alert('Appointments marked as completed!'); window.location='doctor.php';</script>";
    }
}

// Fetch only pending appointments
$query = "
    SELECT appointments.id, appointments.date, appointments.time, users.username AS patient_name
    FROM appointments
    INNER JOIN users ON appointments.patient_id = users.id
    WHERE appointments.doctor_id = ? AND appointments.completed = 0
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Doctor Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Welcome! Dr. <?php echo htmlspecialchars($doctor_name); ?> 👨‍⚕️</h1>
        <h2>Your Appointments</h2>
        <?php if ($result->num_rows > 0): ?>
            <form method="post">
                <table>
                    <tr>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Completed</th>
                    </tr>
                    <?php while ($appointment = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['date']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['time']); ?></td>
                            <td>
                                <input type="checkbox" name="appointment_ids[]" value="<?php echo $appointment['id']; ?>">
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
                <button type="submit" name="complete_appointments" class="complete-btn">Mark as Completed</button>
            </form>
        <?php else: ?>
            <p>No pending appointments.</p>
        <?php endif; ?>
        <a href="logout.php" class="btn">Logout</a>
    </div>
</body>
</html>
