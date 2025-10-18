# LinuxMonitor
Real time monitoring for Linux systems based on the excellent tool of Xavier Berger (https://github.com/XavierBerger/RPi-Monitor). Since Xavier was not able to maintain RPi-monitor since it last release in 2017 that piece of software is outdated. With LinuxMonitor there is an up-to date version of a real time monitoring system for Linux kernels.

LinuxMonitor consists of 3 components:
- Python Daemon
- MySQL database
- PHP webdashboard

Please let me know in case you see some bugs!

jaapeldoorn.

# Installation

## Database (MySQL/MariaDB)
Login as root user in MySQL/MariaDB.
Create users and grant rights to the users (change password to own passwords and use them later in de daemon and webdashboard config files):
```SQL
CREATE DATABASE linuxmonitor CHARACTER SET utf8mb4;
CREATE USER 'linmon_writer'@'localhost' IDENTIFIED BY 'WRITE31f34ert4ggs';
GRANT SELECT, INSERT, UPDATE, DELETE ON linuxmonitor.* TO 'linmon_writer'@'localhost';
CREATE USER 'linmon_reader'@'localhost' IDENTIFIED BY 'READerdg453rg43sg';
GRANT SELECT ON linuxmonitor.* TO 'linmon_reader'@'localhost';
FLUSH PRIVILEGES;
```
Create tables with following command:
```bash
mysql -u root -p rpimonitor < /etc/LinuxMonitor/install/scheme.sql
```

## Python Daemon
Intall required packages:
```bash
sudo apt update
sudo apt install -y python3-venv python3-pip git
```

Save LinuxMonitor to /etc/LinuxMonitor; create a virtual environment:
```bash
sudo mkdir -p /etc/LinuxMonitor
cd /etc/LinuxMonitor
git clone https://github.com/jaapeldoorn/LinuxMonitor.git
cd /opt/LinuxMonitor/daemon
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

Configure the daemon:
```bash
sudo cp /etc/LinuxMonitor/daemon/config.yaml.orig /etc/LinuxMonitor/daemon/config.yaml
sudo nano /etc/LinuxMonitor/config.yaml
```

Start the service:
```bash
sudo cp /etc/LinuxMonitor/daemon/linuxmonitor.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable linuxmonitor
sudo systemctl start linuxmonitor
sudo systemctl status linuxmonitor
```

## PHP webdashboard
Install Apache2 in case no webserver is installed:
```bash
sudo apt install -y apache2
```

Install required package:
```bash
sudo apt install -y php php-mysql
```

Configure webdashboard:
```bash
sudo cp /etc/LinuxMonitor/web/config.php.orig /etc/LinuxMonitor/web/config.php
sudo nano /etc/LinuxMonitor/web/config.php
```

Create virtual link to webfiles and assign correct rights:
```bash
sudo ln -s /etc/LinuxMonitor/web /var/www/html/linuxmonitor
sudo chown www-data:www-data /var/www/html/linuxmonitor
```

Make sure webserver will follow Simlinks. Apache2 Virtual host config file should contain `Options FollowSymLinks`. Reload Apache2 configfiles with `sudo service apache2 reload` in case configuration has been changed.
Example Apache2 config file (/etc/apach2/sites-enabled/default.conf:
```
<VirtualHost *:80>
        ServerName dummy.com
        ServerAlias www.dummy.com
        DocumentRoot /var/www/html

        ServerAdmin admin@dummy.com

        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined

        Options FollowSymLinks
</VirtualHost>
```

# System Requirements
- Linux host
- Python 3.10+ (using match function)
- MySQL 8+ or MariaDB 10.5+
- PHP 8.1+ with pdo_mysql
- Webserver (Apache or Nginx)

# Licence
LinuxMonitor is available with the **MIT-licence**.
The logo used is from Hopstarter (https://www.iconarchive.com/artist/hopstarter.html)
The icons used are from FontAwesome (https://fontawesome.com/) and modified slightly.
