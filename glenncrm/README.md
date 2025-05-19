# Glenn CRM System

A simple yet powerful Customer Relationship Management (CRM) system built with PHP and MySQL.

## Features

- **Customer Management:** Add, view, update, and delete customer records
- **Lead Management:** Track leads from initial contact to closing
- **Sales Tracking:** Record sales and monitor performance
- **Interaction Logging:** Document all customer interactions
- **Automated Reminders:** Set follow-up reminders for tasks
- **Analytics Dashboard:** Visualize key metrics with interactive charts
- **User Management:** Role-based access control (admin and user roles)
- **Responsive Design:** Works on both desktop and mobile devices

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache recommended)
- Modern web browser

## Installation Instructions

1. **Database Setup**
   - Create a MySQL database named `glenncrm_db`
   - Import the `database-table.sql` file to create the necessary tables
   - (Optional) Run the provided seed data script `database-seed.sql` for test users and sample records

2. **Configuration**
   - Update the database connection details in `config/database.php` if needed
   - Set your site URL in the `BASE_URL` constant

3. **Web Server Setup**
   - Place all files in your web server's document root or a subdirectory
   - Ensure the web server has write permissions for the `uploads/` and `logs/` directories

4. **Default Credentials**
   - Admin user: **admin** / **admin123**

## Directory Structure

```
glenncrm/
├── assets/             # Static assets
│   ├── css/            # Stylesheets
│   ├── js/             # JavaScript files
│   └── img/            # Images
├── config/             # Configuration files
├── includes/           # Core PHP functions and components
├── logs/               # Error logs and activity logs
├── pages/              # Main page content files
│   ├── customers/      # Customer-related views
│   ├── leads/          # Lead-related views
│   └── ...             # Other page sections
├── uploads/            # Uploaded files
└── index.php           # Main application entry point
```

## User Guide

### Dashboard
The dashboard provides a quick overview of your CRM activity with key metrics:
- Total customers, leads, and sales
- Recent leads and upcoming reminders
- Sales and lead conversion charts

### Customers
Manage your customer information:
- View a list of all customers with filtering options
- Add new customers with contact information
- View detailed customer profiles with interaction history
- Edit customer details or delete records (admin only)

### Leads
Track potential sales opportunities:
- Create leads associated with customers
- Update lead status as they progress through your sales pipeline
- Assign leads to specific users for follow-up
- Record expected close dates and potential values

### Interactions
Document all customer communications:
- Log calls, emails, meetings and other interactions
- Associate interactions with specific customers and leads
- View interaction history by customer

### Reminders
Never miss a follow-up:
- Set reminders for future actions
- Receive notifications for upcoming tasks
- Mark reminders as completed when done

### Reports
Analyze your business performance:
- View sales reports with charts and graphs
- Analyze lead conversion rates
- Track user activity and performance

## Security Features

- Password hashing using PHP's password_hash() function
- Input sanitization to prevent SQL injection
- CSRF protection for forms
- Role-based access control

## Support

For issues or questions, please contact the system administrator.

## License

This CRM system is proprietary and may not be redistributed without permission.