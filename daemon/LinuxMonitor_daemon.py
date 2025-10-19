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
from datetime import datetime

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
            if sampling_cycle == 1 or sampling_cycle % 10 == 0:
                cur = db.conn.cursor(dictionary=True)
                cur.execute(F"SELECT id, command, regex, frequency, modification FROM metrics WHERE run=1 AND keystr LIKE '{cfg['host_label']}%'")
                run_metrics = cur.fetchall()
                cur.close()
                logger.debug(f"Current run strategy from MySQL server: {str(run_metrics)}")
            for m in run_metrics:
                try:
                    if sampling_cycle % m['frequency'] == 0:
                        logger.debug("Attempt to run command: " + str(m['command'] ))
                        sub_proc_obj = subprocess.run(str(m['command']), shell=True, capture_output=True, text=True, timeout=25)
                        output = sub_proc_obj.stdout
                        logger.debug("Output of run command: "+str(output))
                        re_result = re.findall(m['regex'], output)
                        logger.debug(f"Output from regex: {re_result}")
                        if re_result: #RegEx match found
                            logger.debug(f"Will start modification {m['modification']} for run-metric {m['id']}")
                            match m['modification']:
                                case None:
                                    value = float(re_result[0])
                                case 0:
                                    value = float(re_result[0])
                                case 1: #Convert from sec to hour
                                    try:
                                        value = float(re_result[0]) / 3600
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case 2: #Divide by 1000
                                    try:
                                        value = float(re_result[0]) / 1000
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case 3: #Divide by 1024
                                    try:
                                        value = float(re_result[0]) / 1024
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case 4: #Convert from sec to day
                                    try:
                                        value = float(re_result[0]) / 86400
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case 5: #Subtract 2 values and divide by 1024
                                    try:
                                        value = ( float(re_result[0]) - float(re_result[1]) ) / 1024
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case 6: #Calculate BPS network trafic
                                    try:
                                        get_last_value = db.conn.cursor()
                                        get_last_value.execute(f"SELECT `string`, ts from `txt-status` where metric_id = {m['id']};")
                                        last_values = get_last_value.fetchall()
                                        get_last_value.close
                                        cur_ts = datetime.now().strftime('%Y-%m-%d %H:%M:%S.%f')
                                        cur_value = float(re_result[0])
                                        if len(last_values) == 0:
                                            #first datapoint
                                            value = 0
                                        else:
                                            #BPS calculation
                                            last_value = float(last_values[0][0])
                                            last_ts = last_values[0][1]
                                            delta_value = (cur_value - last_value )
                                            delta_time = (datetime.now() - last_ts).total_seconds()
                                            value = delta_value / delta_time
                                        set_last_value = db.conn.cursor()
                                        set_last_value.execute(f"INSERT INTO `txt-status`(metric_id, ts, string) VALUES ({m['id']}, '{cur_ts}', {cur_value}) ON DUPLICATE KEY UPDATE metric_id = {m['id']}, ts = '{cur_ts}', string = {cur_value}")
                                        set_last_value.close
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}: {e}")
                                case 7: #Calculate BPS network trafic and multiply by -1
                                    try:
                                        get_last_value = db.conn.cursor()
                                        get_last_value.execute(f"SELECT `string`, ts from `txt-status` where metric_id = {m['id']};")
                                        last_values = get_last_value.fetchall()
                                        get_last_value.close
                                        cur_ts = datetime.now().strftime('%Y-%m-%d %H:%M:%S.%f')
                                        cur_value = float(re_result[0])
                                        if len(last_values) == 0:
                                            #first datapoint
                                            value = 0
                                        else:
                                            #BPS calculation
                                            last_value = float(last_values[0][0])
                                            last_ts = last_values[0][1]
                                            delta_value = (cur_value - last_value )
                                            delta_time = (datetime.now() - last_ts).total_seconds()
                                            value = (delta_value / delta_time) * -1
                                        set_last_value = db.conn.cursor()
                                        set_last_value.execute(f"INSERT INTO `txt-status`(metric_id, ts, string) VALUES ({m['id']}, '{cur_ts}', {cur_value}) ON DUPLICATE KEY UPDATE metric_id = {m['id']}, ts = '{cur_ts}', string = {cur_value}")
                                        set_last_value.close
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}: {e}")
                                case 88: #Match Found/NotFound
                                    try:
                                        value = 'Active'
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case 99: #Proces a string
                                    try:
                                        value = re_result[0]
                                    except Exception as e:
                                        logger.exception(f"Error during conversion type {m['modification']} with original value {value}")
                                case _:
                                    logger.exception(f"Modification id {m['modification']} not defined (run ID = {m['id']})")
                            if m['modification']==99 or m['modification']==88: #Strings
                                insert_cur = db.conn.cursor()
                                insert_cur.execute("INSERT INTO `txt-status`(metric_id, ts, string) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE metric_id = %s, ts = %s, string = %s", (m['id'], now_ts(), value, m['id'], now_ts(), value))
                                insert_cur.close()
                                logger.debug(f"Run-metric {m['id']} processed with text {value}")
                            else: #Numerics
                                insert_cur = db.conn.cursor()
                                insert_cur.execute("INSERT INTO samples(metric_id, ts, value) VALUES (%s, %s, %s)", (m['id'], now_ts(), value))
                                insert_cur.close()
                        else:
                            if m['modification']==88:
                                insert_cur = db.conn.cursor()
                                insert_cur.execute("INSERT INTO `txt-status`(metric_id, ts, string) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE metric_id = %s, ts = %s, string = %s", (m['id'], now_ts(), value, m['id'], now_ts(), value))
                                insert_cur.close()
                            else:
                                logger.warning(f"No match voor regex '{m['regex']}' on output: {output}")
                except Exception as e:
                    logger.exception(f"Error during excecution of run-metric {m['id']}: {e}")
        except Exception as e:
            logger.exception(f"Error during processing of run-metrics: {e}")


        # Sleep unitl next cyclus
        elapsed = time.time() - start
        logger.info(f"Duration of {sampling_cycle} was {round(elapsed)} sec")
        sleep_for = max(0.0, interval - elapsed)
        time.sleep(sleep_for)

        sampling_cycle = sampling_cycle + 1

if __name__ == '__main__':
    main()
