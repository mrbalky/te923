**Newer versions of te923 tool have this bug fixed; this patch is no longer necessary**

Get the source of te923tool v0.5 from http://te923.fukz.org/ and put it in this directory.

This directory contains a patch to fix the UV index scaling issue with that version of the tool.

Apply the patch to the te923con.c source file with a command like this:
             patch -R te923com.c te923com.c.patch

Then build the te923con according to the instructions on http://te923.fukz.org/

**The patch is only for v0.5 of te923tool**

