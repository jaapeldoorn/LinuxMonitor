#!/bin/bash
exec -a "LinuxMonitor" /etc/LinuxMonitor/daemon/.venv/bin/python /etc/LinuxMonitor/daemon/LinuxMonitor_daemon.py --config /etc/LinuxMonitor/daemon/config.yaml
