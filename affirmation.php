<?php 
session_start();
include 'header.php'; 
include 'db_connection.php';
// Example: logged-in user (replace with your session logic)
$logged_in_user_id = 4; // or from $_SESSION['user_id']

// Fetch current active cycle
$stmt = $pdo->query("SELECT * FROM affirmation_cycle WHERE is_active=1 LIMIT 1");
$currentCycle = $stmt->fetch();
$cycle_id = $currentCycle['cycle_id'] ?? null;

// Handle new affirmation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipient_id'])) {
    $recipient_id = $_POST['recipient_id'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    $sql = "INSERT INTO affirmations (sender_id, recipient_id, cycle_id, subject, message)
            VALUES (:sender_id, :recipient_id, :cycle_id, :subject, :message)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'sender_id' => $logged_in_user_id,
        'recipient_id' => $recipient_id,
        'cycle_id' => $cycle_id,
        'subject' => $subject,
        'message' => $message
    ]);

    $success = true;
}

// Fetch affirmation history
$affirmations = [];
if ($cycle_id) {
    $sql = "SELECT a.*, u1.email AS sender_email, u2.email AS recipient_email
            FROM affirmations a
            JOIN users u1 ON a.sender_id = u1.id
            JOIN users u2 ON a.recipient_id = u2.id
            WHERE a.cycle_id = :cycle_id
            ORDER BY a.submitted_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cycle_id' => $cycle_id]);
    $affirmations = $stmt->fetchAll();
}

// Fetch staff list for recipient dropdown
$staffStmt = $pdo->prepare("SELECT * FROM users WHERE role='user' AND id != :user_id");
$staffStmt->execute(['user_id' => $logged_in_user_id]);
$staffList = $staffStmt->fetchAll();

?>

<div class="body">
    <div class="container">
        <div class="dashboard-history py-3 py-lg-5">
            <div class="row">
                <div class="col-lg-7 col-left">
                    <div class="aff-history">
                        <div class="aff-content">
                            <h5>Affirmation History</h5>

                            <!-- Your existing static affirmation previews -->
                            <div class="affirmation">
                                <img src="https://placehold.co/50" class="img-fluid" alt="">
                                <div class="content">
                                    <span>sent to <strong>Eric</strong></span>
                                    <p>Preview of subject <span>Aug 6</span></p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Soluta, odio!</p>
                                </div>
                            </div>

                            <div class="affirmation">
                                <img src="https://placehold.co/50" class="img-fluid" alt="">
                                <div class="content">
                                    <span>sent to <strong>Eric</strong></span>
                                    <p>Preview of subject <span>Aug 6</span></p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Soluta, odio!</p>
                                </div>
                            </div>

                            <div class="affirmation">
                                <img src="https://placehold.co/50" class="img-fluid" alt="">
                                <div class="content">
                                    <span>sent to <strong>Eric</strong></span>
                                    <p>Preview of subject <span>Aug 6</span></p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Soluta, odio!</p>
                                </div>
                            </div>

                            <div class="affirmation">
                                <img src="https://placehold.co/50" class="img-fluid" alt="">
                                <div class="content">
                                    <span>sent to <strong>Eric</strong></span>
                                    <p>Preview of subject <span>Aug 6</span></p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Soluta, odio!</p>
                                </div>
                            </div>

                            <!-- Fixed modal starts here -->
                            <div class="aff-modal">
                                <!-- Button trigger modal -->
                                <button type="button" class="btn btn-primary custom-btn" data-toggle="modal" data-target="#exampleModalCenter">
                                    <i class="fa-solid fa-plus"></i>
                                </button>

                                <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
                                     aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">New Affirmation</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="aff-title d-sm-flex mb-2">
                                                    <small class="d-block mb-2">You can send one affirmation per cycle. Your message will remain anonymous.</small>
                                                </div>
                                                <div class="aff-form">
                                                    <form id="affirmationForm" method="POST">
                                                        <!-- Recipient -->
                                                        <div class="form-group">
                                                            <label for="recipientSelect">Recipient</label>
                                                            <select name="recipient_id" id="recipientSelect" class="form-control" required>
                                                                <option value="">Select a colleague</option>
                                                                <?php foreach($staffList as $staff): ?>
                                                                    <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['email']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <!-- Subject -->
                                                        <div class="form-group">
                                                            <label for="emailSubject">Subject</label>
                                                            <input type="text" class="form-control" id="emailSubject" name="subject" placeholder="Subject here..." required>
                                                        </div>

                                                        <!-- Message -->
                                                        <div class="form-group">
                                                            <label for="exampleFormControlTextarea1">Message</label>
                                                            <textarea class="form-control" id="exampleFormControlTextarea1" name="message" placeholder="Write your message here..." rows="3" required></textarea>
                                                        </div>

                                                        <!-- Submit Button -->
                                                        <div class="text-center mt-3">
                                                            <button type="submit" class="btn btn-primary">Send</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Confirmation modal (unchanged) -->
                                <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content text-center p-4">
                                            <h5>Your affirmation was submitted — your manager will forward it soon.</h5>
                                            <button type="button" class="btn btn-primary mt-3" data-dismiss="modal">OK</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Fixed modal ends here -->

                        </div>
                        <!--/.aff-content-->
                    </div>
                    <!--/.aff-history-->
                </div>

                <div class="col-lg-5 col-right">
                    <div class="cycle open">
                        <div class="status">
                            <h5>Current Cycle</h5>
                            <h6>OPEN <i class="fa-solid fa-lock-open"></i></h6>
                        </div>

                        <div class="progress my-2" role="progressbar" aria-label="Info example" aria-valuenow="50"
                             aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-info" style="width: 50%"></div>
                        </div>
                        <div class="rem-time">
                            <div class="status">
                                <h6>Time Remaining</h6>
                                <h6>3d 5h 22m</h6>
                            </div>
                        </div>
                    </div>
                    <!--/.cycle-open-->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
