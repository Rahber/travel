<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common.php';
verifyAdminAccess();
includeHeader();

$hostname = EMAIL_HOST;
$username = EMAIL_USERNAME;
$password = EMAIL_PASSWORD;

$inbox = [];
$error = '';

try {
    // Connect to IMAP server
    $inbox = imap_open($hostname, $username, $password);

    if ($inbox) {
        // Get all unread emails
        $emails = imap_search($inbox, 'UNSEEN');

        if ($emails) {
            // Sort emails by date in descending order
            rsort($emails);
            
            // Take only the last 5 emails
            $emails = array_slice($emails, 0, 5);
            
            echo '<div class="container mt-4">';
            echo '<h2>Latest 5 Unread Emails</h2>';
            
            foreach($emails as $email_number) {
                $header = imap_headerinfo($inbox, $email_number);
                $body = imap_fetchbody($inbox, $email_number, 1);
                
                // Decode and sanitize email data
                $subject = isset($header->subject) ? $conn->real_escape_string(imap_utf8($header->subject)) : 'No Subject';
                $from = isset($header->from[0]->mailbox) && isset($header->from[0]->host) ? 
                        $conn->real_escape_string($header->from[0]->mailbox . "@" . $header->from[0]->host) : 'Unknown Sender';
                $from_name = isset($header->from[0]->personal) ? 
                        $conn->real_escape_string(imap_utf8($header->from[0]->personal)) : '';
                $date = date("Y-m-d H:i:s", strtotime($header->date));
                $body = $conn->real_escape_string($body);
                $message_id = isset($header->message_id) ? 
                        $conn->real_escape_string($header->message_id) : '';
                $in_reply_to = isset($header->in_reply_to) ? 
                        $conn->real_escape_string($header->in_reply_to) : '';
                $references = isset($header->references) ? 
                        $conn->real_escape_string($header->references) : '';
                $importance = isset($header->Importance) ? 
                        $conn->real_escape_string($header->Importance) : '';

                // Verify sender exists in users table
                $verify_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if ($verify_stmt === false) {
                    throw new Exception("Failed to prepare verification statement: " . $conn->error);
                }

                $verify_stmt->bind_param("s", $from);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                $verify_stmt->close();

                if ($verify_result->num_rows === 0) {
                    // Log the attempt to skip this email due to sender not found
                    logUserActivity($_SESSION['user_id'], 'skip_email', "Skipped email from: " . htmlspecialchars($from, ENT_QUOTES, 'UTF-8'));
                    echo '<div class="alert alert-warning">Skipping email from: ' . htmlspecialchars($from, ENT_QUOTES, 'UTF-8') . '</div>';
                    continue;
                }
                
                // Prepare and execute insert statement using prepared statements
                $stmt = $conn->prepare("INSERT INTO emails (uid, from_address, from_name, subject, body, received_date, read_status, processed, message_id, in_reply_to, references_emails, importance) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?)");
                
                if ($stmt === false) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
                
                $stmt->bind_param("isssssssss", 
                    $_SESSION['user_id'],
                    $from,
                    $from_name, 
                    $subject,
                    $body,
                    $date,
                    $message_id,
                    $in_reply_to,
                    $references,
                    $importance
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute statement: " . $stmt->error);
                }
                
                $stmt->close();

                // Mark email as read in mailbox
                imap_setflag_full($inbox, $email_number, "\\Seen");

                echo '<div class="card mb-3">';
                echo '<div class="card-header" data-toggle="collapse" data-target="#collapseBody' . $email_number . '" aria-expanded="false" aria-controls="collapseBody' . $email_number . '">';
                echo '<strong>From:</strong> ' . htmlspecialchars($from, ENT_QUOTES, 'UTF-8') . '<br>';
                echo '<strong>Subject:</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '<br>';
                echo '<strong>Date:</strong> ' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
                echo '</div>';
                echo '<div id="collapseBody' . $email_number . '" class="collapse">';
                echo '<div class="card-body">';
                echo '<p class="card-text">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>';
                echo '</div>';
                echo '</div>';
                echo '</div>';

                // Log email access
                logUserActivity($_SESSION['user_id'], 'add_email_to_db', "Added email from: " . htmlspecialchars($from, ENT_QUOTES, 'UTF-8'));
            }
            
            echo '</div>';
        } else {
            echo '<div class="container mt-4">';
            echo '<div class="alert alert-info">No unread emails found.</div>';
            echo '</div>';
        }

        imap_close($inbox);
    }
    
} catch (Exception $e) {
    $error = "Failed to connect to email server: " . $e->getMessage();
    error_log($error);
    echo '<div class="container mt-4">';
    echo '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '</div>';
}

includeFooter();
?>
