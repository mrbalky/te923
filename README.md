**NOTE: Since this hack, the anemometer of my te923 broke, and I ponied up for a Davis Vantage Pro 2.
To be honest, I think the reliability of the te923 stacked up pretty well against the Davis.**


# te923

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

# My old blog posts for how I got here

I took the blog down years ago. Including the content for posterity.

## Weather station - my new toy
For some reason I can't explain, I've wanted a weather station for years.  
A piece in <a href="http://www.wired.com/">Wired</a>  last month pushed me over the edge and I picked up the
 <a href="http://honeywellweatherstations.com/TE923W.html">Honeywell TE923W</a>.  
 It's relatively cheap, and while not as good as a
  <a href="http://www.davisnet.com/weather/products/weather_product.asp?pnum=06152">Davis</a>, 
  accurate enough for me.


The problem with this station, though, is that it's a USB station, and Linux is not supported by 
the software that ships with it.  But I figured I could find a way around that, and indeed I could, 
but it wasn't so straightforward.  Along the way I learned some arcane details about USB on Debian Linux.  
This series of posts is something of a blow-by-blow of the process.

The hardest part was already done for me by <a href="http://www.fukz.de/">Sebastian John</a> 
who created an application that reads the current raw data from the station.  The source is 
available at <a href="http://te923.fukz.org/index">http://te923.fukz.org/index</a>.  
Building the source requires libusb, and the site links to it, but I was able to build with the 
vanilla libusb-dev installed by apt-get:
<pre><code>
apt-get install libusb-dev
</code></pre>

The build creates the executable <em>te923con</em>, which I then installed in <em>/usr/local/bin</em>.

The first time I tried to run it, I got the following error message:
<pre><code>
[mcp:…/te923/te923] te923con -D
Error while setting configuration (-1).
</code></pre>

This indicates insufficient privileges to access the USB device, so to get things going I ran the te923con application as root.  That got me to the next error:
<pre><code>
[mcp:.../te923/te923] sudo ./te923con -D
Error while setting configuration (-16).
</code></pre>
The dmesg shows the following error (all on one line):
<pre><code>
[24464516.452385] usb 2-1: usbfs: interface 0 claimed 
                    by usbhid while 'te923con' sets config #1
</code></pre>
It seems that the TE923 registers itself as a human interface device (HID) for some reason, so the OS tries to treat it a such.  The author of a package of code that creates a web view for the TE923 has the solution on his blog, though without explanation of the problem or why: <a href="http://firewall.haringstad.com/TE923-Frontend/blog/TE923-Frontend-Info/TE923-Frontend%20Blog/B9EF42CC-F942-41DE-ABDF-461549FEFB46.html">http://firewall.haringstad.com/TE923-Frontend/blog/TE923-Frontend-Info/TE923-Frontend%20Blog/B9EF42CC-F942-41DE-ABDF-461549FEFB46.html</a>

He removes the usbhid kernel module:
<pre><code>
sudo rmmod usbhid
</code></pre>

And success:
<pre><code>
[mcp:.../te923/te923] sudo ./te923con -D
[DEBUG] got |07|00|0a|0a|00|0a|0a|00|
[DEBUG] got |07|0a|0a|00|0a|30|00|af|
[DEBUG] got |07|3d|03|57|c0|28|00|35|
[DEBUG] got |05|00|0a|b2|00|66|00|35|
[DEBUG] got |01|5a|0a|b2|00|66|00|35|
[DEBUG] got |02|28|82|b2|00|66|00|35|
[DEBUG] got |07|37|64|c0|96|0a|00|0a|
[DEBUG] got |07|0a|00|0a|0a|00|0a|0a|
[DEBUG] got |07|00|0a|30|00|af|3d|03|
[DEBUG] got |07|57|c0|28|00|35|00|0a|
[DEBUG] got |03|b2|00|66|00|35|00|0a|
[DEBUG] TMP 0 BUF[00]=28 BUF[01]=82 BUF[02]=37
[DEBUG] TMP 1 BUF[03]=64 BUF[04]=c0 BUF[05]=96
[DEBUG] TMP 2 BUF[06]=0a BUF[07]=00 BUF[08]=0a
[DEBUG] TMP 3 BUF[09]=0a BUF[10]=00 BUF[11]=0a
[DEBUG] TMP 4 BUF[12]=0a BUF[13]=00 BUF[14]=0a
[DEBUG] TMP 5 BUF[15]=0a BUF[16]=00 BUF[17]=0a
[DEBUG] UVX   BUF[18]=30 BUF[19]=00
[DEBUG] PRESS BUF[20]=af BUF[21]=3d
[DEBUG] STAT  BUF[22]=03
[DEBUG] WCHIL BUF[23]=57 BUF[24]=c0
[DEBUG] WGUST BUF[25]=28 BUF[26]=00
[DEBUG] WSPEE BUF[27]=35 BUF[28]=00
[DEBUG] WDIR  BUF[29]=0a
[DEBUG] RAINC BUF[29]=00 BUF[30]=b2 BUF[31]=00
1272059734:22.80:37:6.40:96:::::::::986.9:3.0:3:0:10:1.6:1.3:5.7:178
</code></pre>

Hooray!  There are still a few problems to work out with the device interface, and there is a 
bug in the te923con application to fix, but now I have a working interface to my weather station.  


## Getting the weather station online

Weather Underground has a very simple data upload protocol published on their site at <a href="http://wiki.wunderground.com/index.php/PWS_-_Upload_Protocol">http://wiki.wunderground.com/index.php/PWS_-_Upload_Protocol</a>.  Because running child processes and fetching web pages is easy in PHP, I chose it for coding the application to read, parse and upload the weather data.  (All of the code I wrote for this can be <a href="/wp-content/uploads/te923.zip">downloaded in a zip file</a>.)

Sure, there are packages like <a href="http://code.google.com/p/rrdweather/">RRD Weather</a> that can do this too, but where's the fun in that.

The <em>te923con</em> application returns most of the data included in the wunderground protocol directly, so it's a simple matter of parsing it from the application output.  Weather Underground wants imperial units, but maybe someday I'll want proper metric units, so I made it a parse option.

The weather station apparently does not return a calculated dew point, which is part of the upload protocol.  It turns out that calculating dew point is not straighforward.  But many people have implemented calculators.  A guy named <a href="http://www.decatur.de/">Wolfgang Kühn</a> <a href="http://www.decatur.de/javascript/dew/index.html">implemented one in javascript</a> that seemed very thorough, so I did a quick port of it to PHP.

The weather station also returns only the accumulated rainfall total since the station was started, so I implemented a simple array mechanism to retain historical data from the rain counter.  I can then calculate the "rain in last hour" and "rain since midnight" values required by the WUnderground protocol.

From there, it's a simple matter to build the URL string for data upload, and a <em>file_get_contents</em> call to upload and get the success or failure result.

I save the rain counters and the other weather data as PHP code in a cache file that is reloaded with a PHP include the next time the script runs.

WUnderground can accept data as fast as once a second, but the weather station does not update from its sensors with anywhere near that frequency, so I felt scheduling upload once a minute via cron was more than often enough.  The script takes station id, password and cache file name on the command line (all on one line):
<pre><code>
* * * * * php te923WunderUpload.php <station ID> 
                       <wunderground password> <cache file name>
</code></pre>
Because of the permissions issues with the USB device, for now this is scheduled in the root user's crontab.


## Weather Station - fixing the bugs
The first thing I discovered is that the te923con application has a bug in decoding UV index data from the station.  The index jumps from .9 to 10.0.  A simple patch to te923_com.h is required.  This diff output actually is a change to a single line that I split up for clarity here:
<pre><code>@@ -138,7 +138,7 @@
     }
 
     else {
-        data->uv = bcd2int( buf[18] & 0x0F ) / 10.0 + 
               bcd2int( buf[18] & 0xF0 ) + 
               bcd2int( buf[19] & 0x0F ) * 10.0;
+        data->uv = bcd2int( buf[18] & 0x0F ) / 10.0 + 
               bcd2int( ( buf[18] & 0xF0 ) >> 4 ) + 
               bcd2int( buf[19] & 0x0F ) * 10.0;
         data->_uv = 0;
     }
</code></pre>

The next thing to address is the permissions problem.  To this point, the only way to get data from the station was to be root.  Otherwise, you get this error:
<pre><code>
[mcp:…/te923/te923] te923con -D
Error while setting configuration (-1).
</code></pre>
This is a generic issue with USB devices, and I found an item on a wiki (<a href="http://wiki.openstreetmap.org/wiki/USB_Garmin_on_GNU/Linux#Fixing_Device_Permissions">http://wiki.openstreetmap.org/wiki/USB_Garmin_on_GNU/Linux#Fixing_Device_Permissions</a>) about GPS units that got me going.

That page discusses how to set the group ownership on the device, as well as the permissions on the device.  Long story short, I created the device rule set <em>/etc/udev/rules.d/99-te923.rules</em> (all on one line):
<pre><code>
ATTRS{idVendor}=="1130", ATTRS{idProduct}=="6801",
                    MODE="0660", GROUP="plugdev"
</code></pre>
<em>idVendor</em> and <em>idProduct</em> identify the TE923 weather station, <em>mode</em> tells the USB driver to give read/write permissions to the user and group that owns the device, and <em>group</em> tells the driver to assign group ownership to "plugdev".  My user on the machine is a member of that group so I should be OK.

Ask the system to reload the USB rulesets:
<pre><code>
[mcp:.../te923/te923] sudo udevadm control --reload_rules
</code></pre>

And now I can get valid data back from the unit without being root:
<pre><code>
[mcp:.../cwanek/cronjobs] te923con
1273414489::::59:::::::::1003.1:5.0:3:0:9:0.4:0.0:11.7:215
</code></pre>
Unfortunately, though, some readings (specifically current temp readings) are empty.  Even wide-open permissions on the device don't help.  This doesn't make sense to me, and I have not yet solved this issue, so I'm still stuck with being root to run <em>te923con</em>.  I'd love to know why it would work only partially.

Still, I don't really want to have root's crontab running the script, so I configured sudo to skip the password prompt for the group <em>plugdev</em> for the te928con application.  With <em>visudo</em>, add:
<pre><code>
%plugdev ALL=NOPASSWD: /usr/local/bin/te923con
</code></pre>

Moving on, the I was still not able to run <em>te923con</em> without removing the USB human interface device module (<em>sudo rmmod usbhid</em>).  While removing it allows access to the te923 station, it would also cause any other HID like a mouse or keyboard to stop functioning.  So the trick is to get the <em>usbhid</em> module to release just the weather station.

There are many sites that document how to get usbhid to unbind a device.  I found <a href="http://lwn.net/Articles/143397/">http://lwn.net/Articles/143397/</a>, which gave me the following command:
<pre><code>
sudo bash -c "echo -n 2-1:1.0 > /sys/bus/usb/drivers/usbhid/unbind"
</code></pre>
<em>bash -c</em> is required so the shell redirection to <em>unbind</em> will succeed.

Digging deeper, I learned (<a href="http://reactivated.net/writing_udev_rules.html">http://reactivated.net/writing_udev_rules.html</a>) that you can configure the unbind to happen immediately after the device is connected, by using the "RUN=" option in the device rules file (this is all on one line in <em>/etc/udev/rules.d/99-te923.rules</em>):
<pre><code>
ATTRS{idVendor}=="1130", ATTRS{idProduct}=="6801",
    MODE="0660", GROUP="plugdev", 
    RUN="/bin/sh -c 'echo -n $id:1.0 > /sys/bus/usb/drivers/usbhid/unbind'"
</code></pre>

So now, apart from the missing data when running as a non-privileged user, the te923 weather station is coexisting with other USB devices, and I am able to schedule the upload script in my own user crontab.


## Weather station - one last thing
It is also possible to read version and status information from the weather station, and the <em>te923con</em> application gives access to this data as well.  It is formatted the same way as the weather data, and so was also trivial to process in PHP.  I wrote a script that gets the station and sensor status, and sends a notification email if one of the sensors has a low battery.  That script is also included in the <a href="/wp-content/uploads/te923.zip">te923 zip file</a>.  Again, I know my PHP skills are weak, so if you have improvements I'd be interested in them.

I scheduled it to run once a day at midnight:
<pre><code>
0 0 * * * sleep 30;php te923Status.php <notify email address>
</code></pre>
The 30 second sleep is intended to offset the status check from the normal weather data check that happens exactly on the minute.

Debian out of the box doesn't relay mail to external domains, so I had to do another tweak here.  To enable forwarding, I reconfigured exim according to <a href="http://pkg-exim4.alioth.debian.org/README/README.Debian.etch.html ">http://pkg-exim4.alioth.debian.org/README/README.Debian.etch.html</a>.

It's not really the proper way to create a real relay server, but since I'm behind a firewall and only using the server for this purpose, I didn't feel it necessary to do more.  But the email does look suspicious to the receiving mail system, so if you send to an address outside your domain (like gmail, for example), the mail is likely to be determined as spam.  You'll need to create whatever filters necessary at the recipient account to avoid this.

And that's it so far.  I expect I'll delve into <a href="http://oss.oetiker.ch/rrdtool/">RRDTool</a> and <a href="http://code.google.com/p/rrdweather/">RRDWeather</a> now to see if I can create graphs of readings Weather Underground does not (like humidity, for example).
