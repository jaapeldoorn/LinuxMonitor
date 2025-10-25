# LinuxMonitor configuration parameters

This file will show the details of the configuration of LinuxMonitor

## Daemon configuration
The daemon configuration file is stored in /etc/LinuxMonitor/daemon/config.yaml.
Changes in the configurations are effective after a restart of the daemon with sudo service linuxmonitor restart.

**interval_seconds:**
The interval between each run of the daemon in seconds. 
The standard setting is 60. 
With this settings the daemon checks every 60 seconds whether a command should be executed or not (as configured in the database).

### mysql

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

### dbCleanup
As the metrics table can grow significant over time a separate python script is available to reduce the data for a pre-defined period to one sample point per time unit. This python script can be run with the following command `/etc/LinuxMontor/daemon/dbCleanup.sh`. This command can be started periodically with `crontab -e`.

**toQuarter**
The number of days after which the data will be aggegrated to one datapoint per quarter of an hour. This number should be smaller than toHour.

**toHour**
The number of days after which the data will be aggegrated to one datapoint per hour. This number should be smaller than toDay.

**toDay**
The number of days after which the data will be aggegrated to one datepoint per day.

**host_label**
The name of this system in Linux monitor.
The default value is "System1".
This parameters selects which commands will run from the database. Only the commands that have a name that starts with the host_label will be executed on this systems. This enables the possebility to store the data for diffent systems into one database. 
There is no need to keep the host_label identical to the linux hostname.

## MySQL table configuration
There are 4 tabels that are linked together:
- metrics
- samples
- txt-status
- views
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
8. Divide by 1024x1024 (Conversion from kByte to GByte or Byte to MByte)
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
This table consists of 5 fields which all will be auto populated by the daemon:
**id**
An autonumbered field to identify unique records.

**metric_id**
A numeric field that holds the related ID from the metrics table.

**ts**
A date time field with the moment that this measurement was performed. This time stamp will be on the horizontal axis of the trend graphs.

**value**
The outcome of the modified regex of the run command. This data will be plot in the trend graphs.

**aggregated**
This field is used during cleanup of this table. Default value is false.

### txt-status table
This table consists of 3 fields which all will be auto populated by the daemon:

**metric_id**
A numeric field that holds the related ID from the metrics table.

**ts**
A date time field with the moment that this measurement was performed. This time stamp will be on the horizontal axis of the trend graphs.

**string**
The outcome of the modified regex of the run command. For this table the values are typically strings.

### views table
This table contains the names for each view. The table consists of two fields. Both of the fields need to be filled in manually.

**view_id**
The number of the view as entered in the metrics table. For each unique number in the view field of the metrics table a record with this number is needed in this table.

**view_name**
The name of the view. This name will be visualized as the title of the graph on the monitoring page of the PHP webdashboard.

## PHP webdashboard configuration
This file contains 4 sections:
1. App related settings
2. Database related settings
3. Colors related to specific text values
4. Sections in the status display.

### App related settings
```
  'app' => [
    'mon_refresh_seconds' => 20,    // refresh interval monitoring UI in seconds
    'stat_refresh_seconds' => 40,    // refresh interval status UI in seconds
    'timezone' => 'Europe/Amsterdam',
    'default_device' => 'Server1', // default device in monitoring UI
    'default_minutes' => 360, // default time window monitoring UI in minutes
    'FontAwesomeID' => '4j34t35396', // personal ID from FontAwesome to use their collection
  ],
```
**mon_refresh_seconds**
This parameter is used to determine the interval for the monitoring page of the PHP webdashboard. The default value is 20. This will result in a reload of the monitoring page every 20 seconds.

**stat\_refresh\_seconds**
This parameter is used to determine the interval for the status page of the PHP webdashbaord. The default value is 40. This will result in a reload op the status page by every 40 seconds.

**timezone**
To avoid timezone problems this website will present the date in the configured timezone. This parameter will this timezone. A valid timezone is 'Europe/Amsterdam'

**default_device**
This parameter will hold the host\_label that will be loaded by default. This parameter will be stored in the following format: 'host\_label'.

**default_minutes**
This parameter will hold the default time window for the graphs on the monitor page. Valid options are:
- 60 for 1 hour
- 360 for 6 hours
- 1440 for 1 day
- 10080 for 1 week
- 43200 for 1 month
- 131500 for 3 months
The other options (with a longer duration) are not possible to prevent server performance issues.

**FontAwesomeID**
The ID needed to use icons from the FontAwesome collection. Registration is free. The ID can be found via the 'Set up icons in a project with 1 line of code' link on https://fontawesome.com/kits. The code is the underscore part of the following text:
```
<script src="https://kit.fontawesome.com/__________.js" crossorigin="anonymous"></script>
```

### Database related settings
An example of this part of the config file is:
```
  'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'linuxmonitor',
    'user' => 'linmon_reader',
    'pass' => 'Readgagqeqg435',
    'charset' => 'utf8mb4',
  ],
```

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

### Colors for batches
The colors to assign to specific values on batches. Example of the config is:
```
  'labels' => [
    'active' => 'bg-success',
    'inactive' => 'bg-danger'
  ],
```
In the example the value inactive will be colored with the color bg-danger. Lines with new values can be added. Please make sure that all lines end with a comma with the exeption of the last line.
Valid values for colors are:
- bg-primary: blue
- bg-secondary: gray
- bg-success: green
- bg-danger: red
- bg-warning: yellow
- bg-info: light blue
- bg-light: light gray
- bg-dark: black

### Sections in the status display.
The sections part is the most extensive part of the configuration file. It gives the flexibility to configure the 'tiles' that will be presented on the status page of Linux monitor.
To organize the status page elements can be grouped per section. This is an example of a section with one element:
```
    [
      'title' => 'Uptime',
      'logo' => 'stopwatch',
      'system' => 'all',
      'elements' => [
        [
           'type' => 'text',
           'vartype' => 'float',
           'decimals' => 1,
           'ID' => '58',
           'pre_txt' => 'Uptime: ',
           'post_txt' => ' days'
        ]
      ]
    ],
```
The *title* of this section is Uptime. Together with the logo this title will be presentend as the header of a section.
The *logo* can be configured with two options:
- An icon from the FontAwesome catalog. For this option you need a (free) account at FontAwesome.com. All icons can be found on https://fontawesome.com/search?ic=free&o=r If a specif icon is selected this can be configured by entering the name of the icon in the logo field.
- An image: place this image in the folder `/etc/LinuxMonitor/web/img/` and place the filename as the logo value.
The *system* of this section indicates in which cases this section will be shown on the status page:
- all: this section will be shown independent of the system that is selected in the menu of the status page. Most of the sections need a more specific name as the values stored in the database are in most cases system specific.
- the name of the system: the system name as defined as host-label in the metrics table. This value will be used in most of the cases as the data presented in the sections are in most cases system specific.
The *elements* will contain one or more parts that will present the data.

The *type* of the elements can be one of the following values:
- subtitle
- text
- batch
- bar
- gauge
- UFT-string
- package

In the following paragraphs a more detailed configuration of the elements will be given. Please node that additional elements and sections may be added to the config file. Between each section and element a `,` should be present after the closing `]`. The if no the section will not be followed by another section and if the element will not be followed by another element in this section no `,` should be present after the closing `]`.

##### subtitle
To organize elements of a section a subtitle can be used. The subtitle element consist of the following tags:
```
        [
           'type' => 'subtitle',
           'txt' => 'Upgrade'
        ],
```
The *type* is the identifier of the type of the element. The *txt* is the string that will be shown as subtitle.

#### text
To present a value from the database as text the element `text` can be used. The configuration of a text element consists of the following elements:
```
        [
           'type' => 'text',
           'vartype' => 'float',
           'decimals' => 1,
           'ID' => '58',
           'pre_txt' => 'Uptime: ',
           'post_txt' => ' days'
        ],
```
The *type* is the identifier of the type of the element. The *vartype* can have two values:
- float: to present numbers
- string: to present text
The *decimals* tag indicates the number of decimals to be used to present the value. This tag can be ommitted for `'vartype' => 'string'` as it has no function for strings.
The *ID* presents the record ID in the metrics table for the parameter measured. The last value collected for this metric will be shown on the screen.
The *pre_txt* and *post_txt* are respectively the text to be printed before the value represented by `ID`. Both tags can be removed from the config file if no text has to be shown before and/or after the value.

#### batch
In case a metric resulted in a text string the value of this string can be visualized with a colored area around this text. E.g. a red area for 'inactive' and a green area for 'active'.
The configuration of a batch element is relative short:
```
        [
           'type' => 'badge',
           'ID' => '80'
        ],
```
The *type* is the identifier of the type of the element.
The *ID* presents the record ID in the metrics table for the parameter measured. The last value collected for this metric will be shown on the screen.
The colors of the values are already defined above in the Colors for batches section of the configuration file.

#### bar

```
        [
           'type' => 'bar',
           'ID-part' => '67',
           'ID-total' => '65'
        ],
```

#### gauge

```
        [
           'type' => 'gauge',
           'ID1' => '109',
           'ID2' => '110',
           'ID3' => '111',
           'min' => '0',
           'max' => '5',
           'decimals' => 2
        ],
```

#### UFT-string

```
        [
           'type' => 'UFT-string',
           'ID-used' => '117',
           'ID-total' => '65',
           'decimals' => 0,
        ],
```

#### package

```
        [
           'type' => 'package',
           'source' => 'RaspiServ3_autoremove.txt',
           'pre_txt' => 'Autoremove',
           'post_txt' => 'packages'
        ],
```
