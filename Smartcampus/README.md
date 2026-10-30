<<<<<<< HEAD
# smartcampusconnect
=======
# Smart Campus Connect

A comprehensive web platform for Chinhoyi University of Technology students, providing accommodation booking, internship applications, bus ticket booking, and exam venue location services.

## Features

### Core Modules

1. **Student Accommodation Booking** ✅ COMPLETE
   - Landlords can list properties with images and details
   - Students can search, filter, and book accommodations
   - Price negotiation feature between students and landlords
   - **Pay Now Zimbabwe Payment Integration** - Full payment gateway
   - **Complete Accommodation Management** - Edit, delete, and manage listings
   - **Advanced Image Management** - Upload, preview, and remove images
   - Real-time availability updates and toggling
   - Booking confirmation and cancellation
   - Landlord-side booking management

### Payment System (NEW) 💳
   - **Pay Now Zimbabwe Integration** - Complete payment gateway
   - Multiple payment methods: EcoCash, OneMoney, TeleCash, Bank Cards
   - Secure payment processing with callback verification
   - Payment status tracking and notifications
   - Automatic booking confirmation upon successful payment
   - Payment history and receipts

2. **Internship Application**
   - Employers can post internship opportunities
   - Students can create professional profiles and apply
   - Resume upload and cover letter submission
   - Application tracking and status updates

3. **Bus Booking System**
   - Bus companies can manage routes and schedules
   - Students can book holiday travel tickets
   - Real-time seat availability
   - Automated booking confirmations

4. **Exam Venue Locator**
   - GPS-enabled venue location
   - Google Maps integration for directions
   - Course-specific exam schedules
   - Real-time navigation assistance

### User Roles

- **Students**: Access all four modules
- **Landlords**: Manage property listings and bookings
- **Employers**: Post internships and review applications
- **Bus Companies**: Manage routes and bookings
- **Administrators**: System management and oversight

## Technology Stack

- **Backend**: PHP 7+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **UI Framework**: Bootstrap 5
- **Maps**: Google Maps API
- **Payment Gateway**: Pay Now Zimbabwe API
- **Icons**: Font Awesome
- **Additional Libraries**: jQuery, Chart.js

## Setup Instructions

### 1. Database Setup
```bash
# Import the database schema
mysql -u root -p smartcampus < db_schema.sql

# Or run the setup script
php setup.php
```

### 2. Configuration
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smartcampus');
define('DB_PORT', 3307);
define('GOOGLE_MAPS_API_KEY', 'YOUR_API_KEY');

// Pay Now Zimbabwe Configuration
define('PAYNOW_INTEGRATION_ID', 'YOUR_INTEGRATION_ID');
define('PAYNOW_INTEGRATION_KEY', 'YOUR_INTEGRATION_KEY');
define('PAYNOW_SANDBOX', true); // Set to false for production
define('PAYNOW_RETURN_URL', 'http://localhost/Smartcampus/payment_success.php');
define('PAYNOW_RESULT_URL', 'http://localhost/Smartcampus/api/paynow_callback.php');
```

**Note**: To get your Pay Now Zimbabwe credentials:
1. Sign up for a merchant account at [Pay Now Zimbabwe](https://paynow.co.zw/)
2. Get your Integration ID and Integration Key from the dashboard
3. Update the constants above with your actual credentials

### 3. Directory Permissions
Ensure upload directories exist and are writable:
```bash
mkdir -p uploads/accommodations
mkdir -p uploads/internships
chmod 755 uploads/
chmod 755 uploads/accommodations
chmod 755 uploads/internships
```

### 4. Start the Application
- Access via: `http://localhost/Smartcampus`
- Default port: 5173 (or configured XAMPP port)

## File Structure
```
Smartcampus/
├── api/                      # API endpoints
│   ├── book_accommodation.php # Create bookings
│   ├── negotiate_price.php    # Price negotiation
│   ├── check_notifications.php
│   └── load_accommodations.php
├── includes/
│   ├── config.php            # Database config & helpers
│   ├── header.php            # Navigation & layout
│   └── footer.php
├── css/
│   └── style.css
├── js/
│   └── script.js
├── uploads/                  # User uploaded files
├── accommodation.php         # Browse & book accommodations
├── add_accommodation.php     # Landlords add properties
├── manage_bookings.php       # Landlords manage bookings
├── my_bookings.php          # Students view bookings
├── dashboard.php            # Role-based dashboard
├── login.php
├── register.php
├── logout.php
├── db_schema.sql
└── README.md
```

## Key Features Implemented

### Accommodation Booking System
1. **For Landlords**:
   - `add_accommodation.php` - Add new properties with images
   - `manage_bookings.php` - Review and confirm/reject bookings
   - Dashboard stats for listings and bookings

2. **For Students**:
   - `accommodation.php` - Search and filter properties
   - `my_bookings.php` - View and manage bookings
   - Booking modal with date selection
   - Price negotiation requests

3. **Backend APIs**:
   - `api/book_accommodation.php` - Create booking requests
   - `api/negotiate_price.php` - Send price negotiation
   - `api/load_accommodations.php` - Paginated search results

### Database Tables
- `accommodations` - Property listings
- `accommodation_bookings` - Student bookings
- `landlords` - Landlord profiles
- `students` - Student profiles
- `users` - System users
- `notifications` - System notifications

## Booking Workflow

### Student Perspective
1. Login to account
2. Browse accommodations on `accommodation.php`
3. Click "Book Now" on desired property
4. Select check-in and check-out dates
5. Submit booking request to landlord
6. View booking status in `my_bookings.php`
7. Can cancel pending bookings

### Landlord Perspective
1. Create account and profile
2. Add properties via `add_accommodation.php`
3. View pending bookings in `manage_bookings.php`
4. Confirm or reject booking requests
5. Receive notifications for price negotiations
6. View booking statistics on dashboard

## API Endpoints

### Booking Endpoints
- `POST api/book_accommodation.php` - Create a booking
- `POST api/negotiate_price.php` - Send price negotiation request

### Data Endpoints
- `GET api/load_accommodations.php` - Get paginated accommodations
- `GET api/check_notifications.php` - Get unread notifications count

## Security Features
- CSRF token protection on all forms
- SQL injection prevention with prepared statements
- Input sanitization on all user inputs
- Session-based authentication
- Role-based access control
- Password hashing with PHP's password_hash()

## Testing Accounts
After running `insert_sample_data.php`:

**Student Account**:
- Email: student@example.com
- Password: password123

**Landlord Account**:
- Email: landlord@example.com
- Password: password123

**Admin Account**:
- Email: admin@example.com
- Password: password123

## Troubleshooting

### Database Connection Errors
- Check DB_PORT in `includes/config.php` (usually 3306 or 3307)
- Verify MySQL is running
- Check credentials in config.php

### Image Upload Issues
- Ensure `uploads/` directory exists and is writable
- Check file permissions: `chmod 755 uploads/`
- Verify MAX_UPLOAD_SIZE in PHP

### Booking Not Working
- Clear browser cache and cookies
- Verify student profile exists
- Check notifications table exists in database
- Enable error reporting: `error_reporting(E_ALL);`

## Development Notes

### Adding New Features
1. Create API endpoint in `api/` folder
2. Update relevant page with UI
3. Add database validation
4. Test with CSRF protection enabled
5. Add error handling and notifications

### Database Modifications
- Always backup database before changes
- Update schema in `db_schema.sql`
- Test migration on fresh install
- Update `insert_sample_data.php` if needed

## Browser Support
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## License
© 2024 Chinhoyi University of Technology

## Support
For issues or feature requests, contact the development team.

## Installation

1. **Prerequisites**
   - XAMPP or similar PHP development environment
   - MySQL database
   - Google Maps API key

2. **Setup**
   ```bash
   # Clone or download the project files to your XAMPP htdocs directory
   # Import the database schema
   mysql -u root -p < db_schema.sql

   # Update database configuration in includes/config.php
   # Add your Google Maps API key to the HTML head section
   ```

3. **Configuration**
   - Update database credentials in `includes/config.php`
   - Add Google Maps API key in the HTML templates
   - Configure file upload directories permissions

## Database Schema

The system uses a normalized MySQL database with the following main tables:
- `users` - User accounts and authentication
- `students`, `landlords`, `employers`, `bus_companies` - Role-specific data
- `accommodations` - Property listings
- `internships` - Job postings
- `buses` - Transport routes
- `exams` - Examination schedules
- `notifications` - System notifications
- `messages` - User communication

## Security Features

- Password hashing with bcrypt
- CSRF protection
- Input sanitization and validation
- SQL injection prevention with prepared statements
- Session management
- File upload restrictions

## Modern Features

- Responsive design for mobile and desktop
- AJAX-powered dynamic content loading
- Real-time notifications
- GPS location services
- Image upload and management
- Search and filtering capabilities
- Price negotiation system
- Automated booking confirmations

## API Endpoints

- `/api/check_notifications.php` - Check unread notifications
- `/api/negotiate_price.php` - Handle price negotiations
- `/api/apply_internship.php` - Process internship applications
- `/api/book_bus.php` - Handle bus ticket bookings

## File Structure

```
/
├── index.php                 # Home page
├── login.php                 # User login
├── register.php              # User registration
├── dashboard.php             # User dashboard
├── accommodation.php         # Accommodation module
├── internship.php            # Internship module
├── bus.php                   # Bus booking module
├── exam.php                  # Exam locator module
├── admin.php                 # Admin panel
├── logout.php                # User logout
├── includes/                 # Common includes
│   ├── config.php           # Database configuration
│   ├── header.php           # HTML head and navigation
│   └── footer.php           # HTML footer
├── api/                     # AJAX API endpoints
├── css/                     # Stylesheets
│   └── style.css            # Custom styles
├── js/                      # JavaScript files
│   └── script.js            # Custom scripts
├── uploads/                 # File uploads directory
└── db_schema.sql            # Database schema
```

## Usage

1. **For Students**:
   - Register and create a profile
   - Browse and book accommodations
   - Apply for internships
   - Book bus tickets
   - Locate exam venues

2. **For Landlords**:
   - List properties with details and images
   - Manage booking requests
   - Handle price negotiations

3. **For Employers**:
   - Post internship opportunities
   - Review and manage applications

4. **For Bus Companies**:
   - Add and manage bus routes
   - Track bookings and revenue

5. **For Administrators**:
   - Monitor system usage
   - Manage users and content
   - View reports and statistics

## Contributing

This is a group project for Chinhoyi University of Technology. For contributions or modifications, please ensure compliance with the project requirements and maintain code quality standards.

## License

This project is developed for educational purposes at Chinhoyi University of Technology.
>>>>>>> 71b88f2 (Initial commit)
