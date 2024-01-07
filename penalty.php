<?php

$servername = "localhost";
$user_name = "u406414437_mylibro";
$Password = "Mylibro123";
$database = "u406414437_mylibro";

$conn = new mysqli($servername, $user_name, $Password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require 'PHPMailer-6.8.1/src/Exception.php';
require 'PHPMailer-6.8.1/src/PHPMailer.php';
require 'PHPMailer-6.8.1/src/SMTP.php';

$query = "SELECT * FROM approved_borrow_requests WHERE borrow_status = 'Borrowed' AND penaltyGranted = ''";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $borrowedId = $row['borrow_id'];
    $borrower = $row['borrower_username'];
    $borrowerUserID = $row['borrower_user_id'];
    $dueDate = strtotime($row['due_date']);
    $currentTimestamp = time();

    if ($dueDate < $currentTimestamp) {
        $escapedBorrower = mysqli_real_escape_string($conn, $borrower);

        $sql = "SELECT * FROM users WHERE username = '$escapedBorrower'";
        $result1 = mysqli_query($conn, $sql);

        if ($result1->num_rows > 0) {
            $row1 = mysqli_fetch_assoc($result1);
            $penalty = $row1['penalty'];

            $newPenalty = '';

            if ($penalty == '') {
                $newPenalty = 'First Offense';
            } else if ($penalty == 'First Offense') {
                $newPenalty = 'Second Offense';
            } else if ($penalty == 'Second Offense') {
                $newPenalty = 'Third Offense';
            }

            if ($newPenalty !== '') {
                $sqlUpdate = "UPDATE users SET penalty = '$newPenalty' WHERE username = '$escapedBorrower'";
                $sqlUpdate .= "; UPDATE approved_borrow_requests SET penaltyGranted = '$newPenalty' WHERE borrow_status ='Borrowed' AND borrow_id = '$borrowedId'";
                
                $sqlNotif = "INSERT INTO notifications (sender_user_id, receiver_user_id, notification_message, read_status, notif_timestamp)values('100001', '$borrowerUserID', 'Penalty Warning: $newPenalty please return the book at the due time', 'UNREAD', now())";
                $resultNotif = mysqli_query($conn, $sqlNotif);

                if (!$resultNotif) {
                    echo "Error inserting notification: " . mysqli_error($conn);
                }
                $sqlEmail = "SELECT * FROM users WHERE username = '$borrower'";
                $result2 = mysqli_query($conn, $sqlEmail);

                if ($result2->num_rows > 0) {
                    $row2 = mysqli_fetch_assoc($result2);
                    $email = $row2['email'];

                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'mylibrolibrarymanagementsystem@gmail.com';
                        $mail->Password   = 'wnmm wsxb ksoq rgac';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port       = 587;

                        $mail->setFrom('mylibrolibrarymanagementsystem@gmail.com', 'MyLibro - Virtual Library Management');
                        $mail->addAddress($email, 'User Name');

                        $mail->isHTML(true);
                        $mail->Subject = 'Penalty';
                        $mail->Body = '
                            <!DOCTYPE html>
                            <html lang="en">
                            <head>
                                <meta charset="UTF-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                <title>Penalty Warning</title>
                            </head>
                            <body style="font-family: Roboto, Arial;">
                                <table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                                    <tr>
                                        <td align="center" bgcolor="#4ca847" style="padding: 40px 0 30px 0;">
                                            <img src="https://i.ibb.co/7tP7sN1/logov1.png" alt="MyLibro - Virtual LMS" width="200" height="200" style="display: block;">
                                            <h2 style="font-size: 24px; color: #333333; margin-top: 10px;">MyLibro - Library Management System</h2>
                                            <h2 style="font-size: 32px; color: #333333; margin-top: 30px;">Penalty Warning</h2>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                            <p style="font-size: 16px; color: #333333;">Good Day! Dear User,</p>
                                            <p style="font-size: 16px; color: #333333;">Your Penalty Warning: <strong>' . $newPenalty . '</strong></p>
                                            <p style="font-size: 16px; color: #333333;">Please comply with the rules of the library and return the book on time.</p>
                                            <p style="font-size: 10px; color: #333333;">Note: If you do not recognize this activity, disregard this email.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#4ca847" style="padding: 10px 30px; color: #ffffff; font-size: 12px; text-align: center;">
                                            <b>MyLibro - Virtual Library Management System &copy; 2023</b>
                                        </td>
                                    </tr>
                                </table>
                            </body>
                            </html>
                        ';

                        if ($mail->send()) {
                            echo "Email sent successfully!";
                        } else {
                            echo "Error sending email: {$mail->ErrorInfo}";
                        }
                    } catch (Exception $e) {
                        echo "Error creating PHPMailer instance: {$e->getMessage()}";
                    }
                }

                // Execute the update query
                $resultUpdate = mysqli_multi_query($conn, $sqlUpdate);

                if (!$resultUpdate) {
                    echo "Error updating penalty: " . mysqli_error($conn);
                }

            }
        }

        // Process the borrowed_id as needed
        echo "Processing borrowed_id: $borrowedId\n";
    }
}

mysqli_close($conn);

?>
