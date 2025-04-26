⚠️ This repository has been deprecated and is no longer maintained.
Please visit [dpviz](https://github.com/madgen78/dpviz) for the latest version and updates.⚠️







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

### Installing the Module

1. **Log into FreePBX**, then navigate to **Admin > Module Admin**.
2. Click **Upload Modules**.
3. **Download the module** from the following link: [Download cpviz 1.0.14](https://github.com/madgen78/cpviz/archive/refs/heads/1.0.14.zip).

#### Upload the Module:
4. Set the upload type to **"Upload (From Hard Disk)"**.
5. Click **Choose File** to select the downloaded file, then click **Upload (From Hard Disk)**.
6. After the upload, click the **"Local Module Administration"** link.

#### Install the Module:
7. Scroll down to **Dial Plan Visualizer** under the **Reports** section and click it.
8. Click the **Install** action.
9. Finally, click the **Process** button at the bottom of the page.


### How to Use the Module
1. **Log in to your PBX** and navigate to **Reports > Dial Plan Visualizer**.
2. **Select or search for an inbound route** using the side menu.

#### Highlighting Paths:
- Click **Highlight Paths**, then click on a node or edge to highlight it (links are inactive).
- **Exported images** will include the highlighted paths.
- When you're done, click **Remove Highlights** to clear the highlights.

#### Navigation:
- **Pan** by holding down the left mouse button.
- **Zoom** using the mouse wheel.

#### Additional Features:
- **Hover** over a path to highlight the entire path from start to end.
- **Click** on a destination to open it in a new tab.
- **Click** on a "Match: (timegroup)" to open it in a new tab.
- To export, click the **"Export as ... .png"** button.

### License
[This modules code is licensed as GPLv3+](http://www.gnu.org/licenses/gpl-3.0.txt)

### Issues
* No known issues at this time.

Please send bug reports by email to adam.volchko@gmail.com
