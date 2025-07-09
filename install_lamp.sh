#!/bin/bash

# 🔧 Your Raspberry Pi IP
MY_IP="10.0.7.108"

echo "🔄 Updating package list..."
sudo apt update && sudo apt upgrade -y

echo "🌐 Installing Apache2..."
sudo apt install apache2 -y

echo "📦 Installing PHP and required modules..."
sudo apt install php libapache2-mod-php php-mysql -y

echo "🛢️ Installing MariaDB Server and Client..."
sudo apt install mariadb-server mariadb-client -y

echo "✅ Enabling and starting Apache2 and MariaDB..."
sudo systemctl enable apache2
sudo systemctl start apache2
sudo systemctl enable mariadb
sudo systemctl start mariadb

echo "🔐 Securing MariaDB installation..."
sudo mysql_secure_installation

echo "👤 Creating MariaDB user for web access..."
sudo mariadb <<EOF
CREATE USER IF NOT EXISTS 'webuser'@'localhost' IDENTIFIED BY 'StrongPass123!';
GRANT ALL PRIVILEGES ON *.* TO 'webuser'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

echo "📄 Creating PHP info page as phpinfo.php..."
echo "<?php phpinfo(); ?>" | sudo tee /var/www/html/phpinfo.php > /dev/null

echo "🔗 Creating PHP-MariaDB connection test file (dbtest.php)..."
cat <<EOF | sudo tee /var/www/html/dbtest.php > /dev/null
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

\$host = 'localhost';
\$user = 'webuser';
\$password = 'StrongPass123!';

\$conn = new mysqli(\$host, \$user, \$password);
if (\$conn->connect_error) {
    die("❌ Connection failed: " . \$conn->connect_error);
}
echo "✅ Connected successfully to MariaDB!";
\$conn->close();
?>
EOF

echo "📦 Installing phpMyAdmin..."
sudo apt install phpmyadmin -y

echo "🔗 Linking phpMyAdmin to Apache (if not auto-linked)..."
if [ ! -e /var/www/html/phpmyadmin ]; then
    sudo ln -s /usr/share/phpmyadmin /var/www/html/phpmyadmin
fi

# Allow remote connections on MariaDB
MARIADB_CNF="/etc/mysql/mariadb.conf.d/50-server.cnf"

if grep -q "^bind-address" $MARIADB_CNF; then
  sudo sed -i 's/^bind-address.*/bind-address = 0.0.0.0/' $MARIADB_CNF
else
  echo "bind-address = 0.0.0.0" | sudo tee -a $MARIADB_CNF
fi

sudo systemctl restart mariadb

echo "✅ MariaDB configured to allow remote connections."


echo ""
echo "🎉 LAMP stack installed successfully on IP: \$MY_IP"
echo "--------------------------------------------------"
echo "🔹 PHP Info Page:       http://\$MY_IP/phpinfo.php"
echo "🔹 DB Connection Test:  http://\$MY_IP/dbtest.php"
echo "🔹 phpMyAdmin:          http://\$MY_IP/phpmyadmin"
echo "🔹 phpMyAdmin Login:    webuser / StrongPass123!"
echo "--------------------------------------------------"
