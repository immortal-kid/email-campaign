<?php
// templates/campaign-list.php

// This file expects a variable named $campaigns to be available,
// which should be an array of associative arrays (each representing a campaign).

if (!isset($campaigns) || !is_array($campaigns) || empty($campaigns)) {
    echo '<p>No campaigns found.</p>';
    return;
}
?>

<div class="campaigns-container">
    <h2>Marketing Campaigns</h2>
    <a href="create-campaign.php" class="btn-create">Create New Campaign</a>
    <div class="campaigns-grid">
        <?php foreach ($campaigns as $campaign): ?>
            <div class="campaign-card">
                <h3><?php echo htmlspecialchars($campaign['name']); ?></h3>
                <p><strong>Status:</strong> <span class="status-<?php echo strtolower(htmlspecialchars($campaign['status'])); ?>"><?php echo htmlspecialchars($campaign['status']); ?></span></p>
                <p><strong>Start Date:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($campaign['startDate']))); ?></p>
                <p><strong>End Date:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($campaign['endDate']))); ?></p>
                <div class="campaign-actions">
                    <a href="view-campaign.php?id=<?php echo urlencode($campaign['id']); ?>">Details</a> |
                    <a href="edit-campaign.php?id=<?php echo urlencode($campaign['id']); ?>">Edit</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .campaigns-container {
        margin: 20px;
        font-family: Arial, sans-serif;
    }
    .btn-create {
        display: inline-block;
        background-color: #007bff;
        color: white;
        padding: 8px 15px;
        text-align: center;
        text-decoration: none;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    .btn-create:hover {
        background-color: #0056b3;
    }
    .campaigns-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
    .campaign-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        background-color: #fff;
    }
    .campaign-card h3 {
        margin-top: 0;
        color: #333;
    }
    .campaign-card p {
        font-size: 0.9em;
        line-height: 1.5;
        color: #555;
    }
    .campaign-actions a {
        margin-right: 10px;
        text-decoration: none;
        color: #007bff;
    }
    .campaign-actions a:hover {
        text-decoration: underline;
    }
    .status-active { color: #28a745; font-weight: bold; }
    .status-pending { color: #ffc107; font-weight: bold; }
    .status-completed { color: #6c757d; font-weight: bold; }
    .status-draft { color: #17a2b8; font-weight: bold; }
</style>