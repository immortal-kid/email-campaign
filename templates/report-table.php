<?php
// templates/report-table.php

// This file expects a variable named $reportData to be available,
// which should be an array of associative arrays (rows).
// It also expects $reportHeaders to be an array of column names.

if (!isset($reportData) || !is_array($reportData) || empty($reportData)) {
    echo '<p>No report data available.</p>';
    return; // Exit if no data
}

if (!isset($reportHeaders) || !is_array($reportHeaders)) {
    // Attempt to infer headers from the first row if not provided
    $reportHeaders = array_keys(current($reportData));
}
?>

<div class="report-container">
    <h2>Financial Report Summary</h2>
    <table>
        <thead>
            <tr>
                <?php foreach ($reportHeaders as $header): ?>
                    <th><?php echo htmlspecialchars($header); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reportData as $row): ?>
                <tr>
                    <?php foreach ($reportHeaders as $header): ?>
                        <td><?php echo htmlspecialchars($row[$header] ?? 'N/A'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
    /* Basic styling for the report table */
    .report-container {
        margin: 20px;
        font-family: Arial, sans-serif;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    th {
        background-color: #f2f2f2;
    }
    tr:nth-child(even) {
        background-color: #f9f9f9;
    }
</style>