# LinuxMonitor configuration parameters

This file will show the details of the configuration of LinuxMonitor

## Daemon configuration
The daemon configuration file is stored in /etc/LinuxMonitor/daemon/config.yaml.
Changes in the configurations are effective after a restart of the daemon with sudo service linuxmonitor restart.

**interval_seconds:**
The interval between each run of the daemon in seconds. 
The standard setting is 60. 
With this settings the daemon checks every 60 seconds whether a command should be executed or not (as configured in the database).

**retention_days:**
Currently not in use
The standard setting is 31
This setting has currently no function

**log_level:**
This determine how much detail will be logged to the journal.
The standard setting is info. Other settings are (from more detail to less detail) debug, info, warning, error, critical
With this setting can be traced when the runs are started. 

**host:**
This refers to the host on which the MySQL server is operating.
The default value is localhost
Please note that this parameter must also be configured in the PHP webdashboard config.

**port:** 
This refers to the port the MySQL server is listening.
The default value is 3306
Please note that this parameter must also be configured in the PHP webdashboard config.

**database:** 
This refers to the database where the tables for LinuxMonitor are located.
The default value is linuxmonitor
Please note that this parameter must also be configured in the PHP webdashboard config.

**user:** 
The MySQL user that will write data into the MySQL database.
The default value is linmon_writer.
This username can be changed in MySQL.

**password:** 
The password of the MySQL user 
There is not default value. The original value should be replaced by another strong password. This password MUST be idential to the password defined in the MySQL database.
This password can be changed in MySQL

**host_label:**
The name of this system in Linux monitor.
The default value is "System1".
This parameters selects which commands will run from the database. Only the commands that have a name that starts with the host_label will be executed on this systems. This enables the possebility to store the data for diffent systems into one database. 
There is no need to keep the host_label identical to the linux hostname.

## MySQL table configuration
There are 4 tabels that are linked together:
- metrics
- samples
- txt-status
- views (pending implementation)
All tables are linked to the metrics table. 

### Metrics table
The metrics table is the central repository how to run, process and report all performance metrics of one or more linux servers.
Changes in the metrics table are effective within 10 runs of the deamon or after a reload of the daemon. (The deamon checks the database every 10 cycly).

The table consist of the following fields:
**id**
An autofilled field with increasing numbers to identifie all the records in this table. This number is the run-ID mentioned in the logging of the daemon.

**keystr**
This field consists of two parts: host\_label.identifier 
The daemon will run all lines where the hostname part of the field is identical to the host\_label in the configuration of the daemon. A daemon on another system can have another host\_label while using this same datatable.
The identifier can be used to 
**name**
The name of the series of measurements that will be displayed in the legend of the graph.

**unit**
The unit field can consist the unit of measure of the measured value. This value is currently not displayed on the user interface of the PHP webdashboard.

**description**
This field can be used to describe what this record will do. This is only for internal purpose. This value is currently not displayed on the user interface of the PHP webdashboard.

**command**
This field holds the command that need to be executed by the daemon. As the daemon is running as root user there is no need to add sudo as a precursor of the command.

**regex**
This fields contains the regex that is used to retrieve the relevant information. The outcome of this regex is stored in the samples table in case of numbers and in the txt-status table for text. For more information on the format of regex please refer to the cheatsheet on https://regexr.com/. 

**modification**
Various options are available to process data retrieved from the regex step. If no modification need to take place this field should be kept NULL (preferably) or 0. Other options are:
```
1. Divide by 3600 (Conversion from seconds to hours)
2. Divide by 1000
3. Divide by 1024 (Conversion from kByte to MByte or byte to kByte)
4. Divide by 86400 (Conversion fom seconds to days)
5. Substract 2 values and divide by 1024 (to calculate used memory from available memory and free memory)
6. Calculate network trafic in bps
7. Calculate network trafic in bps and make the number negative
88. Return 'active' in case the regex is found and 'inactive' is the regex is not found. (E.g. used to use with netstat -nlt to monitor wheter a service is listening on a port). The result is saved as a string in the txt-status table. Only the last value is available.
99. Save the regex as a string in the txt-value table. Only the last status will be saved.
````
**run**
Indicates whether the command should be executed by the daemon or not.

0. Do NOT run the command
1. Do run the command

**view**
The view in which the data should be displayed. This is a number. The graphs will be displayed in ascending order. Records with the same view number are presented in the same graph.

**frequency**
The frequency that the daemon will run the command. The frequency of 1 will result in that the command will run every time. A freqency of 2 will result in a measurement in half of the cases. While a frequency of 10 will result in a measurement every 10 cycles.

### Samples table
This table consists of 4 fields which all will be auto populated by the daemon:
**id**
An autonumbered field to identify unique records.

**metric_id**
A numeric field that holds the related ID from the metrics table.

**ts**
A date time field with the moment that this measurement was performed. This time stamp will be on the horizontal axis of the trend graphs.

**value**
The outcome of the modified regex of the run command. This data will be plot in the trend graphs.

### txt-status table
This table consists of 3 fields which all will be auto populated by the daemon:

**metric_id**
A numeric field that holds the related ID from the metrics table.

**ts**
A date time field with the moment that this measurement was performed. This time stamp will be on the horizontal axis of the trend graphs.

**string**
The outcome of the modified regex of the run command. For this table the values are typically strings.

### views table
This table is pending implementation. This table will be used to generate headers above each graph on the monitor page.

## PHP webdashboard configuration
This file contains 4 sections:
1. App related settings
2. Database related settings
3. Global display defaults
4. Sections in the status display.

### App related settings
**mon_refresh_seconds**
This parameter is used to determine the interval for the monitoring page of the PHP webdashboard. The default value is 20. This will result in a reload of the monitoring page every 20 seconds.

**stat\_refresh\_seconds**
This parameter is used to determine the interval for the status page of the PHP webdashbaord. The default value is 40. This will result in a reload op the status page by every 40 seconds.

**timezone**
To avoid timezone problems this website will present the date in the configured timezone. This parameter will this timezone. A valid timezone is 'Europe/Amsterdam'

**default_device**
This parameter will hold the host\_label that will be loaded by default. This parameter will be stored in the following format: 'host\_label'.

**default_minutes**
This parameter will hold the default time window for the graphs onS the monitor page.

### Database related settings
**host**
The hostname of the MySQL server. Default value is 'localhost'.

**port**
The port on which the MySQL server is listening. The default value is 3306.

**dbname**
The database where the Linuxmonitor tabels are stored. The default value is 'linuxmonitor'.

**user**
The username of the user with reading rights to the LinuxMonitor tables. The default value is 'linmon_reader'. Configuration of the username is taken place in MySQL.

**pass**
The password of the user that will read the LinuxMonitor tables. This must be a self defined strong password identical to the password configured in MySQL.

**charset**
The charset used in the MySQL database. The default value is 'utf8mb4'.

### Global display defaults
**decimals**
The number of decimals used in the display on the PHP webdashboard. The default value is 1.

**threshold**
Thresholds used for different colors. The default values are
```
    'thresholds' => [  // colors for default thresholds
      ['upto' => 70, 'color' => '#2ecc71'], // green
      ['upto' => 90, 'color' => '#f1c40f'], // yellow
      ['color' => '#e74c3c'],               // red
```
### Sections in the status display.
This section has to be worked out in more details in future releases.
