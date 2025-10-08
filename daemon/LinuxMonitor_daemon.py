#!/usr/bin/env python3

"""
LinuxMonitor daemon

- Collects system statistics as configured MySQL table Metrics
- Stores configured statistics in MySQL table Metrics

TODO - Periodieke retentie-opruiming
"""

# --------------------------- Imports ---------------------------------
import os
import time                            # current date/time
import datetime as dt                  # date formats
import logging                         # logging
import yaml                            # YAML-config
import argparse                        # parsing command line arguments
import socket
import re                              # regex
import mysql.connector as mysql
from mysql.connector import errorcode
from typing import Dict, List, Tuple
from systemd import journal
import subprocess

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

    def ensure_metric(self, key: str, name: str, unit: str, description: str) -> int:
        self.connect()
        cur = self.conn.cursor()
        cur.execute("SELECT id FROM metrics WHERE `key`=%s", (key,))
        row = cur.fetchone()
        if row:
            cur.close()
            return row[0]
        cur.execute("INSERT INTO metrics(`key`, name, unit, description) VALUES(%s,%s,%s,%s)", (key, name, unit, description))
        metric_id = cur.lastrowid
        cur.close()
        return metric_id

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
            logger.info("Retentie: %d oude samples verwijderd", deleted)

# --------------------------- Main loop --------------------------------

PLUGINS = []


def main():


    # Stel logging in
    logger = logging.getLogger('journal_logger')
    logger.setLevel(logging.INFO)
    logger.addHandler(JournalHandler())

    # Voorbeeldlog
    logger.info("Dit is een info-log naar het systemd journal.")
    logger.error("Dit is een foutmelding naar het journal.")


    #logging.info("LinuxMonitor daemon starting")

    # Get config
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', default='config.yaml', help='Pad naar configuratiebestand')
    args = parser.parse_args()
    with open(args.config, 'r') as f:
        cfg = yaml.safe_load(f)

    # Get host_label
    cfg.setdefault('host_label', socket.gethostname())


    db = DB(cfg)
    interval = int(cfg.get('interval_seconds', 5))
    retention_days = cfg.get('retention_days', 30)

    #logger.info("LinuxMonitor daemon started (interval=%ss, host_label=%s)", interval, cfg['host_label'])

    last_purge = 0.0
    PURGE_EVERY = 3600.0  # elk uur

    sampling_cycle = 1

    # Voorbeeldlogberichten
    #logging.debug("Dit is een debugbericht")
    #logging.info("Dit is een infobericht")
    #logging.warning("Dit is een waarschuwing")
    #logging.error("Dit is een foutmelding")
    #logging.critical("Dit is een kritieke fout")


    while True:
        logger.info("Samping cycle " + str(sampling_cycle) + " started")
        start = time.time()

        # Retentie periodiek
        if (time.time() - last_purge) > PURGE_EVERY:
            try:
                db.purge_old(retention_days)
            except Exception as e:
                logger.exception("Fout bij retentie-opruiming: %s", e)
            last_purge = time.time()


        # Verwerk metrics met run=1 vóór de slaapcyclus
        try:
            db.connect()
            cur = db.conn.cursor(dictionary=True)
            cur.execute("SELECT id, run, command, regex, frequency FROM metrics WHERE run=1")
            run_metrics = cur.fetchall()
            cur.close()
            logger.info(str(run_metrics))
            for m in run_metrics:
                try:
                    if sampling_cycle % m['frequency'] == 0:
                        #logger.info("Attempt to run command: " + str(m['command'] ))
                        output = subprocess.check_output(str(m['command']), shell=True, text=True, stderr=subprocess.DEVNULL, timeout=5).strip()
                        #logger.info("Output: "+str(output))
                        #logger.info("Applying regex: " + str(m['regex'] ))
                        match = re.search(m['regex'], output)
                        if match:
                            value = float(match.group(1))
                            insert_cur = db.conn.cursor()
                            insert_cur.execute("INSERT INTO samples(metric_id, ts, value) VALUES (%s, %s, %s)", (m['id'], now_ts(), value))
                            insert_cur.close()
                            logger.info(f"Run-metric {m['id']} verwerkt met waarde {value}")
                        else:
                            logger.warning(f"Geen match voor regex '{m['regex']}' op output: {output}")
                except Exception as e:
                    logger.exception(f"Fout bij uitvoeren van run-metric {m['id']}: {e}")
        except Exception as e:
            logger.exception(f"Fout bij ophalen/verwerken van run-metrics: {e}")


        # Slaap tot volgende cyclus
        elapsed = time.time() - start
        sleep_for = max(0.0, interval - elapsed)
        time.sleep(sleep_for)

        sampling_cycle = sampling_cycle + 1

if __name__ == '__main__':
    main()
