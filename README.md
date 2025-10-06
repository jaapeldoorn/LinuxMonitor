# LinuxMonitor
Real time monitoring for Linux systems based on the excellent tool of Xavier Berger (https://github.com/XavierBerger/RPi-Monitor). Since Xavier was not able to maintain RPi-monitor since it last release in 2017 that piece of software is outdated. With LinuxMonitor there is an up-to date version of a real time monitoring system for Linux kernels.

LinuxMonitor consists of 3 components:
- Python Daemon
- MySQL database
- PHP webdashboard

Please let me know in case you see some bugs!

jaapeldoorn

# Installation

## Database (MySQL/MariaDB)
Create users and grant rights to the users:
```SQL
CREATE DATABASE linuxmonitor CHARACTER SET utf8mb4;
CREATE USER 'linmon_writer'@'localhost' IDENTIFIED BY 'WRITE31f34ert4ggs';
GRANT SELECT, INSERT, DELETE ON linuxmonitor.* TO 'linmon_writer'@'localhost';
CREATE USER 'linmon_reader'@'localhost' IDENTIFIED BY 'READerdg453rg43sg';
GRANT SELECT ON linuxmonitor.* TO 'linmon_reader'@'localhost';
FLUSH PRIVILEGES;
```
Create tables with following command:
```bash
mysql -u root -p rpimonitor < sql/scheme.sql
```

