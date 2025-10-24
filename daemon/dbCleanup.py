#!/usr/bin/env python3

"""
LinuxMonitor db cleanup

- Reduces size of the samples table by aggregating data
"""

from datetime import datetime, timedelta,timezone  # Date time calcuations
import mysql.connector as mysql           # DB connection
from mysql.connector import errorcode     # SQL error codes
import yaml                               # YAML-config
import logging                            # logging
import argparse                           # parsing command line arguments
from systemd import journal               # logging to journal

# --------------------------- Logging ---------------------------------

class JournalHandler(logging.Handler):
    def emit(self, record):
        journal.send(
            record.getMessage(),
            PRIORITY=record.levelno,
            SYSLOG_IDENTIFIER='LinuxMonitor'
        )

# --------------------------- DB Layer --------------------------------

class DB:
    def __init__(self, cfg):
        self.cfg = cfg
        self.conn = None

    def connect(self):
        if self.conn and self.conn.is_connected():
            return
        self.conn = mysql.connect(
            host=self.cfg['mysql']['host'],
            port=self.cfg['mysql'].get('port', 3306),
            database=self.cfg['mysql']['database'],
            user=self.cfg['mysql']['user'],
            password=self.cfg['mysql']['password'],
            autocommit=True,
        )

# --------------------------- Main loop --------------------------------

def main():
    # Get config
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', default='config.yaml', help='Path to configuration file')
    args = parser.parse_args()
    with open(args.config, 'r') as f:
        cfg = yaml.safe_load(f)

    # Set logging level based on config value
    logger = logging.getLogger('journal_logger')
    log_level_str = cfg.get('log_level', 'INFO').upper()
    log_level = getattr(logging, log_level_str, logging.INFO)
    logger.setLevel(log_level)
    logger.addHandler(JournalHandler())

    logger.info("LinuxMonitor dbCleanup starting")
    print (f"{datetime.now()} - dbCleanup started")

    days_toQuarter = cfg['dbCleanup']['toQuarter']
    days_toHour = cfg['dbCleanup']['toHour']
    days_toDay = cfg['dbCleanup']['toDay']
    dt_now = datetime.now(timezone.utc)

    dt_toQuarter = dt_now - timedelta(days=days_toQuarter)
    dt_toHour = dt_now - timedelta(days=days_toHour)
    dt_toDay = dt_now - timedelta(days=days_toDay)

    db = DB(cfg)
    db.connect()

    # Aggregate to QUARTER between dt_toQuarter and dt_toHour
    cur = db.conn.cursor()
    sql = f"INSERT INTO samples (ts, metric_id, value, aggregated) "\
          "SELECT " \
          "TIMESTAMP(DATE(ts), MAKETIME(HOUR(ts), FLOOR(MINUTE(ts)/15)*15, 0)) AS quarter_start, " \
          "metric_id, " \
          "MAX(value) AS max_value, " \
          "TRUE " \
          "FROM samples " \
          f"WHERE ts >= '{dt_toHour.strftime('%Y-%m-%d %H:%M:%S')}' AND ts < '{dt_toQuarter.strftime('%Y-%m-%d %H:%M:%S')}' AND aggregated = FALSE " \
          "GROUP BY quarter_start, metric_id"
    cur.execute(sql)

    # Remove original (not-aggregated) data between dt_toQuarter and dt_toHour
    #cur.execute(f"DELETE FROM samples WHERE ts >= '{dt_toHour.strftime('%Y-%m-%d %H:%M:%S')}' AND ts < '{dt_toQuarter.strftime('%Y-%m-%d %H:%M:%S')}' AND aggregated = FALSE;")

    # Aggregate to HOUR between dt_toHour and dt_toDay
    cur = db.conn.cursor()
    sql = f"INSERT INTO samples (ts, metric_id, value, aggregated) "\
          "SELECT " \
          "TIMESTAMP(DATE(ts), MAKETIME(HOUR(ts), 0, 0)) AS hour_start, " \
          "metric_id, " \
          "MAX(value) AS max_value, " \
          "TRUE " \
          "FROM samples " \
          f"WHERE ts >= '{dt_toDay.strftime('%Y-%m-%d %H:%M:%S')}' AND ts < '{dt_toHour.strftime('%Y-%m-%d %H:%M:%S')}' AND aggregated = FALSE " \
          "GROUP BY hour_start, metric_id"
    cur.execute(sql)

    # Remove original (not-aggregated) data between dt_toHour and dt_toDay
    cur.execute(f"DELETE FROM samples WHERE ts >= '{dt_toDay.strftime('%Y-%m-%d %H:%M:%S')}' AND ts < '{dt_toHour.strftime('%Y-%m-%d %H:%M:%S')}' AND aggregated = FALSE;")

    # Aggregate to DAY between dt_toHour and dt_toDay
    cur = db.conn.cursor()
    sql = f"INSERT INTO samples (ts, metric_id, value, aggregated) "\
          "SELECT " \
          "TIMESTAMP(DATE(ts), MAKETIME(0, 0, 0)) AS day_start, " \
          "metric_id, " \
          "MAX(value) AS max_value, " \
          "TRUE " \
          "FROM samples " \
          f"WHERE ts < '{dt_toDay.strftime('%Y-%m-%d %H:%M:%S')}' AND aggregated = FALSE " \
          "GROUP BY day_start, metric_id"
    cur.execute(sql)

    # Remove original (not-aggregated) data between dt_toQuarter and dt_toHour
    cur.execute(f"DELETE FROM samples WHERE ts < '{dt_toDay.strftime('%Y-%m-%d %H:%M:%S')}' AND aggregated = FALSE;")

    # Set aggregate for all records to false
    cur.execute('UPDATE samples SET aggregated = FALSE;')

    logger.info("LinuxMonitor dbCleanup completed")
    print (f"{datetime.now()} - dbCleanup completed")

if __name__ == '__main__':
    main()
