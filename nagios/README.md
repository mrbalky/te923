
This directory contains a Nagios check script for the te923 scripts above, as well as a configuration
file for the NRPE daemon to be applied on the host to which the te923 weather station is connected.

**Configure NRPE:**

Add the following line to the NRPE daemon config file /etc/nagios/nrpe.cfg:

```
include_dir=/opt/te923/nagios
```

Or, you could symlink to the cfg file:

```
sudo ln -s /opt/te923/nagios/te923.cfg /etc/nagios/nrpe.d/te923.cfg
```

Then restart the nrpe daemon:

```
sudo service nagios-nrpe-server restart
```

**Configure Nagios:**

You can then add Nagios services on your monitor host.  Drop te923_services.cfg into 
`/etc/nagios3/conf.d`

Then edit `/etc/nagios3/conf.d/te923_services.cfg` to comment out sensors not attached to your
weather station, and add your weather station host to the te923-servers Nagios hostgroup.
