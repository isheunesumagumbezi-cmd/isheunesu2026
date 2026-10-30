#!/bin/bash
# Smart Campus Connect - Quick Setup Script
# Run this script to quickly set up the system

echo "===================================================="
echo "Smart Campus Connect - Quick Setup"
echo "===================================================="
echo ""

# Step 1: Create directories
echo "[1] Creating upload directories..."
mkdir -p uploads/accommodations
mkdir -p uploads/internships
chmod 755 uploads/

# Step 2: Database import
echo "[2] Setting up database..."
echo "   Please ensure MySQL is running on localhost:3307"
echo "   Or modify the port in includes/config.php"
echo ""
echo "   To import database schema, run:"
echo "   mysql -u root -p smartcampus < db_schema.sql"
echo ""

# Step 3: Configuration check
echo "[3] Checking configuration..."
if grep -q "GOOGLE_MAPS_API_KEY" includes/config.php; then
    echo "   ✓ Config file exists"
    echo "   Remember to add your Google Maps API key!"
fi

echo ""
echo "[4] File Permissions..."
chmod 644 css/style.css
chmod 644 js/script.js
chmod 755 api/*.php

echo ""
echo "===================================================="
echo "Setup Complete!"
echo "===================================================="
echo ""
echo "Next Steps:"
echo "1. Start XAMPP/MySQL"
echo "2. Import database: mysql -u root smartcampus < db_schema.sql"
echo "3. Access: http://localhost/Smartcampus"
echo "4. Run healthcheck: http://localhost/Smartcampus/healthcheck.php"
echo ""
echo "See README.md for detailed instructions"
