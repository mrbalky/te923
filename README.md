te923
=====

Mixture of scripts for polling the weather station and uploading to Weather Undergound.

Also a couple of scripts and config files for dealing with the weather station USB device.

Requires te923con: http://te923.fukz.org/

*Sold as:*
* Mebus TE923
* Honeywell TE923W
* Meade TE923W-M
* Irox USB pro
* ... and others

You should just be able to get this code from github and put it in /opt/te923.  All
paths in the scripts assume this location.  The Weather Underground uploader caches
its state to /opt/te923, so permissions will have to allow writing to that dir.

There is a sample crontab file in the misc directory.  Those crons send their output
to /var/log/te923, so that directory should exist and be writable.

There are some configuration files for Nagios monitoring in the nagios directory.
