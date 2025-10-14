#!/usr/bin/env python3

"""
LinuxMonitor daemon

- Collects system statistics as configured MySQL table Metrics
- Stores configured statistics in MySQL table Metrics
- Periodic retention cleanup
"""

# --------------------------- Imports ---------------------------------
#import os
import time                            # current date/time
import datetime as dt                  # date formats
import logging                         # logging
import yaml                            # YAML-config
import argparse                        # parsing command line arguments
import socket                          # retrieval of hostname
import re                              # regex
import mysql.connector as mysql        # DB connection
from mysql.connector import errorcode  # SQL error codes
from typing import Dict, List, Tuple   # variable structures
from systemd import journal            # logging to journal
import subprocess                      # execution of commands

# --------------------------- Logging ---------------------------------

class JournalHandler(logging.Handler):
    def emit(self, record):
        journal.send(
            record.getMessage(),
            PRIORITY=record.levelno,
            SYSLOG_IDENTIFIER='LinuxMonitor'
        )

# --------------------------- Helpers ---------------------------------

def now_ts():
    return dt.datetime.now(dt.timezone.utc)

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

    #def ensure_metric(self, key: str, name: str, unit: str, description: str) -> int:
    #    self.connect()
    #    cur = self.conn.cursor()
    #    cur.execute("SELECT id FROM metrics WHERE `key`=%s", (key,))
    #    row = cur.fetchone()
    #    if row:
    #        cur.close()
    #        return row[0]
    #    cur.execute("INSERT INTO metrics(`key`, name, unit, description) VALUES(%s,%s,%s,%s)", (key, name, unit, description))
    #    metric_id = cur.lastrowid
    #    cur.close()
    #    return metric_id

    def insert_samples(self, rows: List[Tuple[int, dt.datetime, float]]):
        if not rows:
            return
        self.connect()
        cur = self.conn.cursor()
        cur.executemany("INSERT INTO samples(metric_id, ts, value) VALUES(%s,%s,%s)", rows)
        cur.close()

    def purge_old(self, retention_days: int):
        if not retention_days or retention_days <= 0:
            return
        self.connect()
        cur = self.conn.cursor()
        cur.execute("DELETE FROM samples WHERE ts < (UTC_TIMESTAMP(6) - INTERVAL %s DAY)", (retention_days,))
        deleted = cur.rowcount
        cur.close()
        if deleted:
            logger.info("Retention: %d old samples removed", deleted)

# --------------------------- Main loop --------------------------------

PLUGINS = []


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

    logger.info("LinuxMonitor daemon starting")

    # Get host_label
    cfg.setdefault('host_label', socket.gethostname())

    db = DB(cfg)
    interval = int(cfg.get('interval_seconds', 5))
    retention_days = cfg.get('retention_days', 30)

    logger.debug("LinuxMonitor daemon started (interval=%ss, host_label=%s)", interval, cfg['host_label'])

    last_purge = 0.0
    PURGE_EVERY = 3600.0  # every hour TODO

    sampling_cycle = 1

    while True:
        logger.info("Samping cycle " + str(sampling_cycle) + " started")
        start = time.time()

        # Retention period
        if (time.time() - last_purge) > PURGE_EVERY:
            try:
                #db.purge_old(retention_days)
                logger.debug("No retention performed.")
            except Exception as e:
                logger.exception("Fout bij retentie-opruiming: %s", e)
            last_purge = time.time()

        # Proces metrics with run=1 in database
        try:
            db.connect()
            cur = db.conn.cursor(dictionary=True)
            cur.execute("SELECT id, run, command, regex, frequency, modification FROM metrics WHERE run=1")
            run_metrics = cur.fetchall()
            cur.close()
            #logger.debug(str(run_metrics))
            for m in run_metrics:
                try:
                    if sampling_cycle % m['frequency'] == 0:
                        #logger.debug("Attempt to run command: " + str(m['command'] ))
                        output = subprocess.check_output(str(m['command']), shell=True, text=True, stderr=subprocess.DEVNULL, timeout=5).strip()
                        #logger.debug("Output: "+str(output))
                        #logger.debug("Applying regex: " + str(m['regex'] ))
                        re_result = re.search(m['regex'], output)
                        if re_result:
                            value = float(re_result.group(1))
                            #logger.debug(f"Current modification is {m['modification']} for run-metric {m['id']}")
                            match m['modification']:
                                case None:
                                    pass
                                case 0:
                                    pass
                                case 1: #Convert from sec to hour
                                    try:
                                        value = value / 3600
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case 2: #Divide by 1000
                                    try:
                                        value = value / 1000
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case _:
                                    logger.exception(f"Error during excecution of run-metric {m['id']}: {e}")
                            insert_cur = db.conn.cursor()
                            insert_cur.execute("INSERT INTO samples(metric_id, ts, value) VALUES (%s, %s, %s)", (m['id'], now_ts(), value))
                            insert_cur.close()
                            logger.info(f"Run-metric {m['id']} processed with value {value}")
                        else:
                            logger.warning(f"No match voor regex '{m['regex']}' on output: {output}")
                except Exception as e:
                    logger.exception(f"Error during excecution of run-metric {m['id']}: {e}")
        except Exception as e:
            logger.exception(f"Error during processing of run-metrics: {e}")


        # Sleep unitl next cyclus
        elapsed = time.time() - start
        sleep_for = max(0.0, interval - elapsed)
        time.sleep(sleep_for)

        sampling_cycle = sampling_cycle + 1

if __name__ == '__main__':
    main()
