<?php
// templates/contacts-list.php

// This file expects a variable named $contacts to be available,
// which should be an array of associative arrays (each representing a contact).

if (!isset($contacts) || !is_array($contacts) || empty($contacts)) {
    echo '<p>No contacts found.</p>';
    return;
}
?>

<div class="contacts-container">
    <h2>Your Contacts</h2>
    <a href="add-contact.php" class="btn-add">Add New Contact</a>
    <ul>
        <?php foreach ($contacts as $contact): ?>
            <li>
                <strong><?php echo htmlspecialchars($contact['name']); ?></strong> (<?php echo htmlspecialchars($contact['email']); ?>)
                <br>
                Phone: <?php echo htmlspecialchars($contact['phone'] ?? 'N/A'); ?>
                <div class="contact-actions">
                    <a href="view-contact.php?id=<?php echo urlencode($contact['id']); ?>">View</a> |
                    <a href="edit-contact.php?id=<?php echo urlencode($contact['id']); ?>">Edit</a> |
                    <a href="delete-contact.php?id=<?php echo urlencode($contact['id']); ?>" onclick="return confirm('Are you sure you want to delete this contact?');">Delete</a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<style>
    .contacts-container {
        margin: 20px;
        font-family: Arial, sans-serif;
    }
    .contacts-container ul {
        list-style: none;
        padding: 0;
    }
    .contacts-container li {
        border: 1px solid #eee;
        padding: 10px;
        margin-bottom: 10px;
        background-color: #fff;
        border-radius: 5px;
    }
    .contact-actions {
        margin-top: 5px;
        font-size: 0.9em;
    }
    .contact-actions a {
        margin-right: 5px;
        text-decoration: none;
        color: #007bff;
    }
    .contact-actions a:hover {
        text-decoration: underline;
    }
    .btn-add {
        display: inline-block;
        background-color: #28a745;
        color: white;
        padding: 8px 15px;
        text-align: center;
        text-decoration: none;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    .btn-add:hover {
        background-color: #218838;
    }
</style>