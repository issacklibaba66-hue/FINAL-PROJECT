<?php
// Database connection (replace with your credentials)
$host = 'localhost';
$dbname = 'agriculture';
$username = 'root';
$password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get auction ID from URL
$auctionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
//if ($auctionId <= 0) {
 //   die("Invalid auction ID");
//}

// Fetch auction details
$stmt = $pdo->prepare("SELECT * FROM auctions WHERE title = ?");
$stmt->execute([$auctionId]);
$auction = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$auction) {
    die("Auction not found");
}

// Handle bid submission (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bidAmount'])) {
    $bidAmount = (float)$_POST['bidAmount'];
    $bidderId = 1; // Mock user ID; replace with session-based auth
    if ($bidAmount > $auction['current_bid']) {
        // Insert bid
        $stmt = $pdo->prepare("INSERT INTO bids (auction_id, bidder_id, amount) VALUES (?, ?, ?)");
        $stmt->execute([$auctionId, $bidderId, $bidAmount]);
        // Update current bid
        $pdo->prepare("UPDATE auctions SET current_bid = ? WHERE id = ?")->execute([$bidAmount, $auctionId]);
        // Refresh auction data
        $stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ?");
        $stmt->execute([$auctionId]);
        $auction = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'auction' => $auction]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Bid must be higher than current bid']);
        exit;
    }
}

// Function to get updated auction (for polling)
if (isset($_GET['poll'])) {
    $stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ?");
    $stmt->execute([$auctionId]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($auction);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Page</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .bid-form { margin-top: 20px; }
        input, button { padding: 10px; margin: 5px 0; }
        button { background: #28a745; color: white; border: none; cursor: pointer; }
        button:hover { background: #218838; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($auction['title']); ?></h1>
        <p><?php echo htmlspecialchars($auction['description']); ?></p>
        <p>Starting Bid: $<span id="startingBid"><?php echo $auction['starting_bid']; ?></span></p>
        <p>Current Bid: $<span id="currentBid"><?php echo $auction['current_bid']; ?></span></p>
        <p>Ends: <span id="endTime"><?php echo date('Y-m-d H:i:s', strtotime($auction['end_time'])); ?></span></p>
        <p>Status: <span id="status"><?php echo ucfirst($auction['status']); ?></span></p>

        <?php if ($auction['status'] === 'active'): ?>
            <div class="bid-form">
                <input type="number" id="bidAmount" placeholder="Enter bid amount" step="0.01">
                <button onclick="placeBid()">Place Bid</button>
                <div id="message"></div>
            </div>
        <?php else: ?>
            <p>Auction ended.</p>
        <?php endif; ?>
    </div>

    <script>
        let auctionId = <?php echo $auctionId; ?>;
        let currentBid = <?php echo $auction['current_bid']; ?>;

        // Function to place bid via AJAX
        function placeBid() {
            const bidAmount = parseFloat(document.getElementById('bidAmount').value);
            if (isNaN(bidAmount) || bidAmount <= currentBid) {
                showMessage('Bid must be higher than current bid', 'error');
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    updateAuction(response.auction);
                    showMessage('Bid placed successfully!', 'success');
                    document.getElementById('bidAmount').value = '';
                } else {
                    showMessage(response.error, 'error');
                }
            };
            xhr.send('bidAmount=' + bidAmount);
        }

        // Function to poll for updates
        function pollUpdates() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', window.location.href + '?poll=1', true);
            xhr.onload = function() {
                const updatedAuction = JSON.parse(xhr.responseText);
                updateAuction(updatedAuction);
            };
            xhr.send();
        }

        // Update UI with new auction data
        function updateAuction(auction) {
            document.getElementById('currentBid').textContent = auction.current_bid;
            document.getElementById('status').textContent = auction.status.charAt(0).toUpperCase() + auction.status.slice(1);
            currentBid = auction.current_bid;
        }

        // Show message
        function showMessage(text, type) {
            const msgDiv = document.getElementById('message');
            msgDiv.textContent = text;
            msgDiv.className = type;
            setTimeout(() => msgDiv.textContent = '', 3000);
        }

        // Poll every 5 seconds
        setInterval(pollUpdates, 5000);
    </script>
</body>
</html>