```
Dial Plan Visualizer - see a graph of the call flow for
any Inbound Route.
```
### What?
cpviz
This is a module for [FreePBX©](http://www.freepbx.org/ "FreePBX Home Page"), an open source graphical user interface to control and manage [Asterisk(http://www.asterisk.org/ "Asterisk Home Page") phone systems.  FreePBX is licensed under GPL.
The cpviz module shows you a graph of the call flow for any Inbound Route.  End-user PBX support often involves making changes to the flow for inbound calls, or simply asking questions about it (e.g. "Whose phones ring when someone calls X?  When we get a call on Y does it go directly to the IVR or are there Time Conditions applied first?").

### Installing the module
* If upgrading- uninstall the current version first.

* Command line...
Uninstall:
```
fwconsole ma uninstall cpviz
```

Install:
```
fwconsole ma downloadinstall https://github.com/madgen78/cpviz/archive/refs/heads/1.0.13.zip
```

--or--

* Log into FreePBX, go to Admin > Module Admin
* Click Upload Modules
* Download from web: https://github.com/madgen78/cpviz/archive/refs/heads/1.0.13.zip

* Set the type to "Upload (From Hard Disk)"
* Select "Choose File" to select the downloaded file, then click "Upload (From Hard Disk)"
* Click the "local module administration" link
* Scroll down to Dial Plan Visualizer in the Reports section, click it, then click the Install action.
* Click the Process button at the bottom of the page.

### Using the module
* Log in to your PBX, go to Reports > Dial Plan Visualizer
* Select an inbound route from the side menu.

The graph for that route should be displayed.  
The graph is interractive.  
* Hovering over a path will highlight end to end. 
* Clicking on a destination will open it in a new tab. 
* Clicking on a Match:(timegroup) will open it in a new tab. 
* Clicking "Export as ... .png" button

### License
[This modules code is licensed as GPLv3+](http://www.gnu.org/licenses/gpl-3.0.txt)

### Issues
* No known issues at this time.

Please send bug reports by email to adam.volchko@gmail.com
