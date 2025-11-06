#!/bin/bash
sudo aptitude update > /dev/null
sudo aptitude -F%p --disable-columns search ~U > /etc/LinuxMonitor/web/apt/RaspiServ3_upgrade.txt
sudo aptitude -F%p --disable-columns search ~o > /etc/LinuxMonitor/web/apt/RaspiServ3_autoremove.txt

