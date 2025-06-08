=== Email Campaign Pro ===
Contributors: Your Name/Company
Donate link: https://your-website.com/donate
Tags: email, campaign, transactional, scheduling, reporting, smtp, custom post type, action scheduler
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A custom WordPress plugin for sending transactional emails with precise scheduling and detailed reporting.

== Description ==

Email Campaign Pro is a robust WordPress plugin designed to streamline your transactional email needs. It allows you to create and manage email campaigns using a dedicated Custom Post Type, upload recipient lists via Excel/CSV, send emails at a controlled rate using a background process, and track detailed metrics like delivery, open, and bounce rates.

**Key Features:**

* **Custom Post Type (CPT):** Create and manage email campaigns with a familiar WordPress editor interface.
* **Recipient List Upload:** Easily import email addresses from Excel (.xlsx) or CSV (.csv) files. Supports fixed format (Email in Column A, First Name in Column B).
* **Centralized Contact Management:** Unique contacts are stored and managed in a dedicated database table, allowing reuse across campaigns.
* **Precise Scheduling:** Utilizes Action Scheduler for reliable background email sending at a rate of 1 email every 3 seconds.
* **Campaign Control:** Pause, resume, and cancel ongoing campaigns directly from the admin interface.
* **Comprehensive Tracking:** Tracks email delivery status, open rates (via tracking pixel), and bounce rates (via SMTP provider webhooks).
* **Detailed Reporting:** View per-email reports for each campaign, showing delivery, open, and bounce statuses.
* **CAN-SPAM Compliance:** Automatically includes unsubscribe links and configurable physical address in email footers.
* **Integration:** Seamlessly integrates with your existing WP Mail SMTP plugin for email delivery.

== Installation ==

1.  **Download:** Download the plugin ZIP file.
2.  **Upload & Extract:** Upload the `email-campaign.zip` file via the WordPress plugin uploader (Plugins -> Add New -> Upload Plugin) or manually upload the extracted `email-campaign` folder to your `wp-content/plugins/` directory.
3.  **Composer Dependencies:**
    * **IMPORTANT:** Navigate to `wp-content/plugins/email-campaign/` in your terminal or command prompt.
    * Run `composer install`. This will download necessary libraries like PhpSpreadsheet and Action Scheduler into the `vendor/` directory. If you don't have Composer, you'll need to install it first (getcomposer.org).
4.  **Activate:** Activate the plugin through the 'Plugins' menu in WordPress.
5.  **Database Setup:** Upon activation, the plugin will automatically create the necessary custom database tables (`wp_email_campaigns_subscribers`, `wp_email_contacts`, `wp_email_campaigns_logs`).
6.  **SMTP Webhook Configuration (Crucial for Bounce Tracking):**
    * **Identify your SMTP provider:** (e.g., SendGrid, Mailgun, Brevo, Amazon SES).
    * **Log in to your SMTP provider account.**
    * **Locate their "Webhooks" or "Event Notification" settings.**
    * **Add a new webhook URL** for bounce events (and optionally other events like delivered, spam reports).
    * **The webhook URL will be:** `YOUR_WORDPRESS_SITE_URL/wp-json/email-campaign/v1/webhook-bounce`
        * Replace `YOUR_WORDPRESS_SITE_URL` with your actual website URL (e.g., `https://example.com`).
    * **Enable "Bounce" events** (and any other relevant events like "Delivered", "Spam Report") for this webhook.
    * **Save the webhook configuration.**

== Usage ==

1.  **Create a New Campaign:**
    * Go to **Email Campaigns > Add New**.
    * Enter a **Title** for your campaign.
    * Compose your email content using the WordPress editor (supports HTML).
    * In the **Campaign Settings** meta box, enter the **Email Subject** and **Email Pre-Header**.
    * In the **Recipient List** meta box:
        * **Upload Excel/CSV File:** Click "Choose File" and select your recipient list. The file must have **Email in Column A (required)** and **First Name in Column B (optional)**. After uploading, a summary of valid/invalid/duplicate emails will be displayed.
        * *(Note: Direct selection from centralized contacts is a future enhancement. For now, manage contacts via the "Contacts" menu.)*
    * Click **Publish** (or Update if editing). A confirmation popup will appear before the campaign starts sending.
2.  **Manage Contacts:**
    * Go to **Email Campaigns > Contacts**.
    * View, edit, or delete individual contacts.
    * Export all contacts to a CSV file.
    * New contacts from your Excel/CSV uploads are automatically added and deduplicated here.
3.  **View Reports:**
    * Go to **Email Campaigns**.
    * In the campaign list, click on the "View Report" link in the "Report" column for any campaign.
    * This page provides a detailed list of all emails for that campaign, showing delivery status, open status, and bounce status.
    * You can also export the report to CSV.
4.  **Campaign Actions (Pause/Resume/Cancel):**
    * On the **Email Campaigns** list screen or the campaign edit screen (in the "Campaign Status" meta box), you will see buttons to:
        * **Pause:** Stop current email sending and keep remaining emails unsent.
        * **Resume:** Continue sending emails for a paused campaign.
        * **Cancel:** Permanently stop and clear all pending emails for a campaign.

== Frequently Asked Questions ==

= How does the plugin send emails? =
The plugin uses WordPress's `wp_mail()` function, which means it will automatically leverage your existing SMTP setup (e.g., WP Mail SMTP plugin, Post SMTP, etc.). Ensure your WordPress site is correctly configured to send emails.

= How does it handle large email lists? =
The plugin uses Action Scheduler, a reliable background processing library, to send emails at a controlled rate (1 email every 3 seconds). This prevents server overload and ensures stable delivery for lists up to 10,000 emails.

= What if my Excel/CSV file has different column headers? =
The plugin currently assumes a fixed structure: Email in Column A and First Name in Column B. Files with different structures will need to be adjusted before upload.

= How does bounce tracking work? =
Bounce tracking relies on webhooks from your SMTP provider (e.g., SendGrid). You must configure your SMTP provider to send bounce notifications to the plugin's webhook URL (see Installation Step 6).

= Is the plugin CAN-SPAM compliant? =
Yes, the plugin automatically adds an unsubscribe link and a configurable physical address to the footer of every email sent, helping you comply with CAN-SPAM regulations.

= Can I customize the email template? =
The email content is edited using the standard WordPress editor for the `email_campaign` post type. You can use HTML. For more advanced templating (like custom Twig or Blade templates), custom development would be required.

== Changelog ==

= 1.0.0 =
* Initial release of Email Campaign Pro.